<?php

declare(strict_types=1);

namespace App\Services\Vendor;

use App\Models\Shop;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Wallet;
use App\Services\AuditLogger;
use App\Services\Kepercayaan\SkorToko;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Vendor onboarding: application, documents, approval and activation.
 *
 * Approval is the only moment in the platform that mints a shop, a user and a
 * subscription in one go, so it is fully transactional: either the applicant
 * ends up with a shop and a default plan, or nothing is written at all.
 */
final class VendorRegistrationService
{
    private const TIERS = [
        'starter' => 8.00,
        'growth' => 6.00,
        'scale' => 4.50,
        'enterprise' => 3.00,
    ];

    public const DOCUMENT_KINDS = ['identity', 'business_license', 'tax_document', 'bank_letter', 'selfie', 'other'];

    public function submit(array $payload, array $documents = []): object
    {
        return DB::transaction(function () use ($payload, $documents): object {
            $email = strtolower(trim((string) $payload['email']));

            if (User::query()->where('email', $email)->exists()) {
                throw ValidationException::withMessages(['email' => 'Email sudah terdaftar.']);
            }

            if (DB::table('vendor_applications')->where('email', $email)->whereIn('status', ['pending', 'under_review'])->exists()) {
                throw ValidationException::withMessages(['email' => 'Sudah ada pengajuan yang sedang diproses untuk email ini.']);
            }

            $slug = $this->uniqueSlug((string) $payload['shop_name']);
            $tier = $this->tier((float) ($payload['commission_value'] ?? 0));
            $plan = $this->defaultPlan();

            $application = DB::table('vendor_applications')->insertGetId([
                'reference' => $this->reference(),
                'shop_name' => VendorScope::clean($payload['shop_name'], 160),
                'slug' => $slug,
                'owner_name' => VendorScope::clean($payload['owner_name'], 120),
                'email' => $email,
                'phone' => VendorScope::cleanNullable($payload['phone'] ?? null, 32),
                'password' => Hash::make((string) $payload['password']),
                'description' => VendorScope::cleanNullable($payload['description'] ?? null, 2000),
                'category' => VendorScope::cleanNullable($payload['category'] ?? null, 80),
                'city' => VendorScope::cleanNullable($payload['city'] ?? null, 100),
                'province' => VendorScope::cleanNullable($payload['province'] ?? null, 100),
                'postal_code' => VendorScope::cleanNullable($payload['postal_code'] ?? null, 12),
                'address' => VendorScope::cleanNullable($payload['address'] ?? null, 500),
                'bank_name' => VendorScope::cleanNullable($payload['bank_name'] ?? null, 120),
                'bank_account_name' => VendorScope::cleanNullable($payload['bank_account_name'] ?? null, 120),
                'bank_account_number' => VendorScope::cleanNullable($payload['bank_account_number'] ?? null, 64),
                'status' => 'pending',
                'commission_value' => $tier['value'],
                'commission_type' => 'percentage',
                'commission_tier' => $tier['key'],
                'subscription_plan_id' => $plan?->getKey(),
                'submitted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->storeDocuments($application, $documents);

            app(AuditLogger::class)->log('vendor.application.submitted', 'vendor_application', [
                'id' => $application,
            ], [
                'status' => 'pending',
                'commission_tier' => $tier['key'],
            ]);

            return DB::table('vendor_applications')->where('id', $application)->first();
        }, 3);
    }

    public function findByReference(string $reference): ?object
    {
        return DB::table('vendor_applications')->where('reference', $reference)->first();
    }

    /**
     * Approve an application: create the owner account, the shop, the wallet
     * and the default subscription, then flip the application to approved.
     *
     * @return array{user: User, shop: Shop, application: object}
     */
    public function approve(int $applicationId, int $reviewerId): array
    {
        return DB::transaction(function () use ($applicationId, $reviewerId): array {
            $application = DB::table('vendor_applications')->where('id', $applicationId)->lockForUpdate()->first();

            abort_if($application === null, 404);
            abort_if((string) $application->status === 'approved', 422, 'Pengajuan sudah disetujui.');

            $user = User::query()->where('email', $application->email)->lockForUpdate()->first();

            if ($user === null) {
                $user = User::query()->create([
                    'name' => (string) $application->owner_name,
                    'email' => (string) $application->email,
                    'phone' => $application->phone,
                    'password' => (string) $application->password,
                    'role' => 'vendor',
                    'status' => 'active',
                ]);
            }

            $user->forceFill(['role' => 'vendor', 'status' => 'active'])->save();

            $shop = Shop::query()->create([
                'vendor_id' => $user->getKey(),
                'name' => (string) $application->shop_name,
                'slug' => (string) $application->slug,
                'description' => $application->description,
                'email' => $application->email,
                'phone' => $application->phone,
                'address' => $application->address,
                'city' => $application->city,
                'province' => $application->province,
                'postal_code' => $application->postal_code,
                'status' => 'active',
                'is_featured' => false,
                'commission_type' => (string) $application->commission_type,
                'commission_value' => Money::of($application->commission_value)->toDecimal(),
                'bank_name' => $application->bank_name,
                'bank_account_name' => $application->bank_account_name,
                'bank_account_number' => $application->bank_account_number,
            ]);

            Wallet::query()->firstOrCreate(['user_id' => $user->getKey()], ['balance' => 0, 'pending_balance' => 0]);
            $this->startDefaultSubscription($user->getKey(), (int) $shop->getKey(), $application->subscription_plan_id);

            DB::table('vendor_applications')->where('id', $applicationId)->update([
                'status' => 'approved',
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now(),
                'approved_at' => now(),
                'approved_user_id' => $user->getKey(),
                'approved_shop_id' => $shop->getKey(),
                'updated_at' => now(),
            ]);

            app(AuditLogger::class)->log('vendor.application.approved', 'vendor_application', [
                'id' => $applicationId,
                'status' => (string) $application->status,
            ], [
                'status' => 'approved',
                'shop_id' => (int) $shop->getKey(),
            ], $reviewerId);

            return [
                'user' => $user,
                'shop' => $shop,
                'application' => DB::table('vendor_applications')->where('id', $applicationId)->first(),
            ];
        }, 3);
    }

    public function reject(int $applicationId, string $reason, int $reviewerId): object
    {
        return DB::transaction(function () use ($applicationId, $reason, $reviewerId): object {
            $application = DB::table('vendor_applications')->where('id', $applicationId)->lockForUpdate()->first();

            abort_if($application === null, 404);

            DB::table('vendor_applications')->where('id', $applicationId)->update([
                'status' => 'rejected',
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now(),
                'rejection_reason' => VendorScope::clean($reason, 2000),
                'updated_at' => now(),
            ]);

            app(AuditLogger::class)->log('vendor.application.rejected', 'vendor_application', [
                'status' => (string) $application->status,
            ], [
                'status' => 'rejected',
            ], $reviewerId);

            return DB::table('vendor_applications')->where('id', $applicationId)->first();
        }, 3);
    }

    public function attachDocument(int $applicationId, array $payload): object
    {
        $id = DB::table('vendor_application_documents')->insertGetId([
            'vendor_application_id' => $applicationId,
            'kind' => in_array($payload['kind'] ?? 'other', self::DOCUMENT_KINDS, true) ? $payload['kind'] : 'other',
            'path' => (string) $payload['path'],
            'original_name' => VendorScope::cleanNullable($payload['original_name'] ?? null, 190),
            'mime_type' => VendorScope::cleanNullable($payload['mime_type'] ?? null, 120),
            'size' => (int) ($payload['size'] ?? 0),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('vendor_application_documents')->where('id', $id)->first();
    }

    /**
     * Progres KYC bertahap: email → identitas → rekening → verifikasi.
     * Murni komputasi dari baris vendor_applications + dokumen (tanpa migrasi).
     *
     * @return array{level: string, level_index: int, percent: int, done: int, steps: list<array{key: string, label: string, done: bool}>}
     */
    public function kycProgress(object $application, array $documents = []): array
    {
        return app(SkorToko::class)->kyc($application, $documents);
    }

    /**
     * @return list<array{key: string, value: float, label: string}>
     */
    public function tiers(): array    {
        $out = [];

        foreach (self::TIERS as $key => $value) {
            $out[] = [
                'key' => $key,
                'value' => $value,
                'label' => ucfirst($key),
            ];
        }

        return $out;
    }

    /** @return array{key: string, value: float} */
    private function tier(float $commissionPercent): array
    {
        foreach (self::TIERS as $key => $value) {
            if ($commissionPercent >= $value) {
                return ['key' => $key, 'value' => $value];
            }
        }

        return ['key' => 'enterprise', 'value' => self::TIERS['enterprise']];
    }

    /** @param  array<int, array{kind: string, path: string, original_name?: string|null, mime_type?: string|null, size?: int}>  $documents */
    private function storeDocuments(int $applicationId, array $documents): void
    {
        foreach (array_slice($documents, 0, 10) as $document) {
            if (! isset($document['path'])) {
                continue;
            }

            $this->attachDocument($applicationId, $document);
        }
    }

    private function startDefaultSubscription(int $vendorId, int $shopId, mixed $planId): void
    {
        $plan = $planId !== null
            ? SubscriptionPlan::query()->find($planId)
            : $this->defaultPlan();

        if ($plan === null) {
            return;
        }

        $trialDays = (int) ($plan->trial_days ?? 0);
        $endsAt = now()->addDays($trialDays > 0 ? $trialDays : max(1, (int) $plan->billing_days));

        DB::table('vendor_subscriptions')->insert([
            'vendor_id' => $vendorId,
            'shop_id' => $shopId,
            'subscription_plan_id' => $plan->getKey(),
            'status' => $trialDays > 0 ? 'trialing' : 'active',
            'amount_paid' => $trialDays > 0 ? Money::zero()->toDecimal() : Money::of($plan->price)->toDecimal(),
            'starts_at' => now(),
            'ends_at' => $endsAt,
            'auto_renew' => true,
            'payment_method' => 'manual',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function defaultPlan(): ?SubscriptionPlan
    {
        return SubscriptionPlan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('price')
            ->first();
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'toko';

        do {
            $slug = $base.'-'.Str::lower(Str::random(5));
        } while (Shop::query()->where('slug', $slug)->exists() || DB::table('vendor_applications')->where('slug', $slug)->exists());

        return $slug;
    }

    // ── Pendalaman KYC: verifikasi bertahap + expiry + alasan tolak ──
    // Tanpa migrasi: expiry & salinan terenkripsi disimpan di
    // vendor_applications.meta (JSON). Audit TIDAK PERNAH menulis PII
    // (email/nomor rekening/nama file) — hanya id + keputusan + alasan.

    /** Tahapan KYC berurutan: tiap tahap butuh jenis dokumen tertentu. */
    public const KYC_STAGES = [
        'identity' => ['label' => 'Identitas', 'kinds' => ['identity', 'selfie']],
        'business' => ['label' => 'Legalitas usaha', 'kinds' => ['business_license', 'tax_document']],
        'bank' => ['label' => 'Rekening', 'kinds' => ['bank_letter']],
    ];

    /**
     * Verifikasi satu dokumen: setujui (dengan masa berlaku) atau tolak
     * (dengan alasan). Idempoten: keputusan sama → kembalikan apa adanya.
     *
     * @return array{document: object, application: object}
     */
    public function verifyDocument(int $applicationId, int $documentId, string $decision, ?string $note = null, ?string $expiresAt = null, ?int $verifierId = null): array
    {
        return DB::transaction(function () use ($applicationId, $documentId, $decision, $note, $expiresAt, $verifierId): array {
            if (! in_array($decision, ['approved', 'rejected'], true)) {
                throw ValidationException::withMessages(['decision' => 'Keputusan harus approved atau rejected.']);
            }

            $application = DB::table('vendor_applications')->where('id', $applicationId)->lockForUpdate()->first();
            abort_if($application === null, 404);

            $document = DB::table('vendor_application_documents')
                ->where('id', $documentId)
                ->where('vendor_application_id', $applicationId)
                ->lockForUpdate()
                ->first();
            abort_if($document === null, 404);

            $cleanNote = VendorScope::cleanNullable($note, 500);
            $expiry = $expiresAt !== null && trim($expiresAt) !== '' ? trim($expiresAt) : null;

            if ($expiry !== null) {
                try {
                    $date = new \DateTimeImmutable($expiry);
                    $expiry = $date->format('Y-m-d');
                } catch (\Throwable) {
                    throw ValidationException::withMessages(['expires_at' => 'Tanggal kedaluwarsa tidak valid (YYYY-MM-DD).']);
                }
            }

            if ((string) $document->status === ($decision === 'approved' ? 'verified' : 'rejected')
                && (string) ($document->note ?? '') === (string) ($cleanNote ?? '')) {
                return ['document' => $document, 'application' => $application];
            }

            DB::table('vendor_application_documents')->where('id', $documentId)->update([
                'status' => $decision === 'approved' ? 'verified' : 'rejected',
                'note' => $cleanNote,
                'verified_by' => $verifierId,
                'verified_at' => now(),
                'updated_at' => now(),
            ]);

            // Expiry disimpan di meta aplikasi (tanpa kolom baru).
            $meta = $this->readMeta($application->meta ?? null);
            $map = is_array($meta['doc_expiry'] ?? null) ? $meta['doc_expiry'] : [];

            if ($decision === 'approved' && $expiry !== null) {
                $map[(string) $documentId] = $expiry;
            } else {
                unset($map[(string) $documentId]);
            }

            $meta['doc_expiry'] = $map;
            $meta['kyc_stage'] = $this->computeStage($applicationId, $meta);

            DB::table('vendor_applications')->where('id', $applicationId)->update([
                'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
                'rejection_reason' => $decision === 'rejected'
                    ? VendorScope::clean(($document->kind ?? 'Dokumen').': '.($cleanNote ?? 'tidak memenuhi syarat'), 500)
                    : $application->rejection_reason,
                'updated_at' => now(),
            ]);

            // Audit redacted: tanpa PII.
            app(AuditLogger::class)->log('vendor.kyc.document_'.$decision, 'vendor_application', [
                'id' => $applicationId,
            ], [
                'document_id' => $documentId,
                'kind' => $document->kind ?? null,
                'expires_at' => $expiry,
            ], $verifierId);

            return [
                'document' => DB::table('vendor_application_documents')->where('id', $documentId)->first(),
                'application' => DB::table('vendor_applications')->where('id', $applicationId)->first(),
            ];
        }, 3);
    }

    /**
     * Status kedaluwarsa dokumen dari meta (tanpa kolom baru).
     *
     * @return array{expired:list<int>, expiring:list<int>, valid:list<int>}
     */
    public function documentExpiryStatus(object $application, int $warnDays = 30): array
    {
        $meta = $this->readMeta($application->meta ?? null);
        $map = is_array($meta['doc_expiry'] ?? null) ? $meta['doc_expiry'] : [];
        $today = new \DateTimeImmutable('today');
        $warn = $today->modify('+'.max(1, $warnDays).' days');

        $expired = [];
        $expiring = [];
        $valid = [];

        foreach ($map as $docId => $date) {
            try {
                $at = new \DateTimeImmutable((string) $date);
            } catch (\Throwable) {
                continue;
            }

            if ($at < $today) {
                $expired[] = (int) $docId;
            } elseif ($at <= $warn) {
                $expiring[] = (int) $docId;
            } else {
                $valid[] = (int) $docId;
            }
        }

        return ['expired' => $expired, 'expiring' => $expiring, 'valid' => $valid];
    }

    /** Progres KYC bertahap per aplikasi (memakai dokumen existing). */
    public function stagedProgress(int $applicationId): array
    {
        $application = DB::table('vendor_applications')->where('id', $applicationId)->first();
        abort_if($application === null, 404);

        $documents = DB::table('vendor_application_documents')
            ->where('vendor_application_id', $applicationId)
            ->get();

        $expiry = $this->documentExpiryStatus($application);
        $stages = [];

        foreach (self::KYC_STAGES as $key => $stage) {
            $relevant = $documents->whereIn('kind', $stage['kinds']);
            $verified = $relevant->where('status', 'verified')->count();
            $rejected = $relevant->where('status', 'rejected')->count();
            $stages[] = [
                'key' => $key,
                'label' => $stage['label'],
                'done' => $relevant->isNotEmpty() && $verified > 0 && $rejected === 0,
                'verified' => $verified,
                'rejected' => $rejected,
                'total' => $relevant->count(),
            ];
        }

        $done = count(array_filter($stages, fn (array $s): bool => $s['done']));

        return [
            'stages' => $stages,
            'done' => $done,
            'percent' => (int) round($done / max(1, count($stages)) * 100),
            'expired_documents' => $expiry['expired'],
            'expiring_documents' => $expiry['expiring'],
        ];
    }

    /**
     * Simpan salinan terenkripsi data sensitif ke meta (JANGAN log PII).
     * Kolom plaintext dipertahankan untuk kompatibilitas baca existing.
     */
    public function sealSensitive(int $applicationId, ?int $actorId = null): object
    {
        return DB::transaction(function () use ($applicationId, $actorId): object {
            $application = DB::table('vendor_applications')->where('id', $applicationId)->lockForUpdate()->first();
            abort_if($application === null, 404);

            $meta = $this->readMeta($application->meta ?? null);

            foreach (['bank_account_number' => 'bank_enc', 'phone' => 'phone_enc'] as $column => $key) {
                $value = trim((string) ($application->{$column} ?? ''));

                if ($value !== '') {
                    $meta[$key] = \Illuminate\Support\Facades\Crypt::encryptString($value);
                }
            }

            DB::table('vendor_applications')->where('id', $applicationId)->update([
                'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);

            app(AuditLogger::class)->log('vendor.kyc.sealed', 'vendor_application', ['id' => $applicationId], ['sealed' => true], $actorId);

            return DB::table('vendor_applications')->where('id', $applicationId)->first();
        }, 3);
    }

    /** Baca kembali data tersegel (hanya untuk proses yang berhak). */
    public function unsealSensitive(object $application, string $key): ?string
    {
        $meta = $this->readMeta($application->meta ?? null);
        $cipher = $meta[$key] ?? null;

        if (! is_string($cipher) || $cipher === '') {
            return null;
        }

        try {
            return \Illuminate\Support\Facades\Crypt::decryptString($cipher);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array<string,mixed> */
    private function readMeta(mixed $meta): array
    {
        if (is_array($meta)) {
            return $meta;
        }

        if (is_string($meta) && $meta !== '') {
            $decoded = json_decode($meta, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /** Tahap KYC dari dokumen terverifikasi (disimpan di meta). */
    private function computeStage(int $applicationId, array $meta): string
    {
        $documents = DB::table('vendor_application_documents')
            ->where('vendor_application_id', $applicationId)
            ->get();

        foreach (['bank', 'business', 'identity'] as $stage) {
            $kinds = self::KYC_STAGES[$stage]['kinds'];
            $ok = $documents->whereIn('kind', $kinds)->where('status', 'verified')->isNotEmpty();

            if ($ok) {
                continue;
            }

            return $stage === 'bank' ? 'business' : ($stage === 'business' ? 'identity' : 'identity');
        }

        return 'complete';
    }

    private function reference(): string
    {
        do {
            $reference = 'VND-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (DB::table('vendor_applications')->where('reference', $reference)->exists());

        return $reference;
    }
}
