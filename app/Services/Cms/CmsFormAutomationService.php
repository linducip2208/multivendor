<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Models\Coupon;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Schema;

/**
 * Automation sederhana pasca-submission form.
 *
 * ID: Aturan per form disimpan di SystemSetting `cms_form_rules_{key}`
 * (JSON array). Aksi yang didukung bila service/model ada:
 * - email_log: catat email notifikasi ke SystemSetting (tanpa kirim SMTP
 *   agar aman di test; integrator tinggal ganti ke Mail).
 * - coupon: buat kupon personal via Coupon::buatKodePersonal (defensif,
 *   dilewati bila tabel coupons tak ada).
 * - notify: catat notifikasi admin ke SystemSetting log JSON.
 * Semua aksi defensif (try/catch) agar submission tak pernah gagal
 * karena automation.
 *
 * EN: Simple post-submission automations, all defensive.
 */
class CmsFormAutomationService
{
    public const ACTIONS = ['email_log', 'coupon', 'notify'];

    /** @return list<array<string, mixed>> */
    public function rulesFor(string $key): array
    {
        try {
            $raw = SystemSetting::get($this->rulesKey($key), '');
            $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];

            return is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** @param  list<array<string, mixed>>  $rules */
    public function saveRules(string $key, array $rules): array
    {
        $clean = [];
        foreach (array_slice($rules, 0, 10) as $rule) {
            if (! is_array($rule)) {
                continue;
            }
            $action = (string) ($rule['action'] ?? '');
            if (! in_array($action, self::ACTIONS, true)) {
                continue;
            }
            $clean[] = [
                'action' => $action,
                'template' => mb_substr(trim((string) ($rule['template'] ?? 'form_received')), 0, 80),
                'coupon_prefix' => mb_substr(trim((string) ($rule['coupon_prefix'] ?? 'HADIAH')), 0, 12),
                'message' => mb_substr(trim((string) ($rule['message'] ?? '')), 0, 500),
                'is_active' => ! isset($rule['is_active']) || ! empty($rule['is_active']),
            ];
        }
        SystemSetting::set($this->rulesKey($key), json_encode($clean, JSON_UNESCAPED_UNICODE));

        return $clean;
    }

    /**
     * Jalankan semua aturan aktif untuk satu submission.
     *
     * @param  array<string, mixed>  $payload data bersih submission
     * @return array{ran: int, results: list<array<string, mixed>>}
     */
    public function run(string $formKey, array $payload, int $submissionId = 0): array
    {
        $results = [];
        foreach ($this->rulesFor($formKey) as $rule) {
            if (empty($rule['is_active'])) {
                continue;
            }
            $results[] = match ((string) $rule['action']) {
                'email_log' => $this->logEmail($formKey, $rule, $payload, $submissionId),
                'coupon' => $this->makeCoupon($formKey, $rule, $payload, $submissionId),
                'notify' => $this->pushNotify($formKey, $rule, $payload, $submissionId),
                default => ['action' => 'skip', 'ok' => false],
            };
        }

        return ['ran' => count($results), 'results' => $results];
    }

    /** @param  array<string, mixed>  $rule  @param  array<string, mixed>  $payload */
    private function logEmail(string $formKey, array $rule, array $payload, int $submissionId): array
    {
        try {
            $to = (string) ($payload['email'] ?? '');
            $entry = [
                'at' => now()->format('Y-m-d H:i:s'),
                'form' => $formKey,
                'submission_id' => $submissionId,
                'to' => $to !== '' && filter_var($to, FILTER_VALIDATE_EMAIL) ? $to : null,
                'template' => (string) ($rule['template'] ?? 'form_received'),
                'subject' => (string) (SystemSetting::get('email_welcome_subject', 'Terima kasih atas submission Anda') ?? ''),
            ];
            $raw = SystemSetting::get('cms_automation_email_log', '');
            $log = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
            $log = is_array($log) ? $log : [];
            $log[] = $entry;
            SystemSetting::set('cms_automation_email_log', json_encode(array_slice($log, -200), JSON_UNESCAPED_UNICODE));

            return ['action' => 'email_log', 'ok' => true, 'to' => $entry['to']];
        } catch (\Throwable $e) {
            return ['action' => 'email_log', 'ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** @param  array<string, mixed>  $rule  @param  array<string, mixed>  $payload */
    private function makeCoupon(string $formKey, array $rule, array $payload, int $submissionId): array
    {
        try {
            if (! Schema::hasTable('coupons')) {
                return ['action' => 'coupon', 'ok' => false, 'skipped' => 'tabel coupons tak ada'];
            }
            $coupon = Coupon::query()->create([
                'shop_id' => null,
                'code' => Coupon::buatKodePersonal((string) ($rule['coupon_prefix'] ?? 'HADIAH')),
                'title' => 'Hadiah form '.$formKey.' #'.$submissionId,
                'coupon_type' => 'percentage',
                'discount_value' => 10,
                'min_purchase' => 0,
                'max_discount' => 25000,
                'start_date' => now(),
                'end_date' => now()->addDays(14),
                'usage_limit' => null,
                'usage_per_customer' => 1,
                'usage_count' => 0,
                'status' => true,
            ]);

            return ['action' => 'coupon', 'ok' => true, 'code' => $coupon->code];
        } catch (\Throwable $e) {
            return ['action' => 'coupon', 'ok' => false, 'error' => mb_substr($e->getMessage(), 0, 200)];
        }
    }

    /** @param  array<string, mixed>  $rule  @param  array<string, mixed>  $payload */
    private function pushNotify(string $formKey, array $rule, array $payload, int $submissionId): array
    {
        try {
            $raw = SystemSetting::get('cms_automation_notify_log', '');
            $log = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
            $log = is_array($log) ? $log : [];
            $log[] = [
                'at' => now()->format('Y-m-d H:i:s'),
                'form' => $formKey,
                'submission_id' => $submissionId,
                'message' => (string) ($rule['message'] ?? 'Submission baru di form '.$formKey),
            ];
            SystemSetting::set('cms_automation_notify_log', json_encode(array_slice($log, -200), JSON_UNESCAPED_UNICODE));

            return ['action' => 'notify', 'ok' => true];
        } catch (\Throwable $e) {
            return ['action' => 'notify', 'ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function rulesKey(string $key): string
    {
        return 'cms_form_rules_'.preg_replace('/[^a-z0-9_\-]/', '-', strtolower($key));
    }
}
