<?php

declare(strict_types=1);

namespace App\Services\Api;

/**
 * Penguatan token+scope+rate limit (OAuth TIDAK tersedia di arsitektur).
 *
 * Arsitektur: laravel/sanctum (bearer token) + api_keys (X-Api-Key). Tidak ada
 * passport/oauth-server, sehingga alur authorization-code OAuth 2.0 dinyatakan
 * PRASYARAT EKSTERNAL (butuh league/oauth2-server atau Passport). Sebagai ganti,
 * modul ini menegakkan kebijakan: expiry wajib, rotasi, least-privilege
 * (tolak '*'), dan audit scope.
 */
final class TokenHardening
{
    public const DEFAULT_EXPIRY_DAYS = 30;

    public const MAX_EXPIRY_DAYS = 90;

    /**
     * Kebijakan expiry: null -> default 30 hari; >90 hari -> dipangkas 90 hari.
     */
    public static function expiryPolicy(?\DateTimeInterface $requested): \DateTimeImmutable
    {
        $now = new \DateTimeImmutable();
        if ($requested === null) {
            return $now->modify('+'.self::DEFAULT_EXPIRY_DAYS.' days');
        }
        $max = $now->modify('+'.self::MAX_EXPIRY_DAYS.' days');
        $req = \DateTimeImmutable::createFromInterface($requested);

        return $req > $max ? $max : $req;
    }

    /** Tolak wildcard '*' kecuali pemilik admin; kembalikan scope aman. */
    public static function safeScopes(array $scopes, bool $isAdmin = false): array
    {
        $clean = array_values(array_unique(array_filter(array_map(
            static fn ($s): string => strtolower(trim((string) $s)),
            $scopes
        ))));
        if (! $isAdmin) {
            $clean = array_values(array_filter($clean, static fn (string $s): bool => $s !== '*'));
        }

        return $clean === [] ? ['read'] : $clean;
    }

    /** True bila token perlu dirotasi (umur > 60 hari atau tanpa expiry). */
    public static function needsRotation(?string $createdAt, ?string $expiresAt): bool
    {
        if ($expiresAt === null || trim($expiresAt) === '') {
            return true;
        }
        if ($createdAt === null || trim($createdAt) === '') {
            return false;
        }
        try {
            $age = time() - (int) strtotime($createdAt);
        } catch (\Throwable) {
            return false;
        }

        return $age > 60 * 86400;
    }

    /** Audit: daftar masalah least-privilege pada satu set scope. */
    public static function auditScopes(array $scopes): array
    {
        $issues = [];
        if (in_array('*', $scopes, true)) {
            $issues[] = 'wildcard';
        }
        if (in_array('admin', $scopes, true)) {
            $issues[] = 'admin_scope';
        }
        if ($scopes === [] || $scopes === ['read']) {
            $issues[] = 'read_only_ok';
        }

        return $issues;
    }
}
