<?php

declare(strict_types=1);

namespace App\Services\Webhooks;

use Illuminate\Support\Str;

/**
 * Signs and verifies outbound webhook requests.
 *
 * The exact scheme is exposed as {@see self::SCHEME} so the admin developer
 * screen and the API documentation render the same text a consumer needs.
 */
final class WebhookSigner
{
    public const ALGORITHM = 'sha256';

    public const TOLERANCE_SECONDS = 300;

    public const SIGNATURE_HEADER = 'X-Webhook-Signature';

    public const TIMESTAMP_HEADER = 'X-Webhook-Timestamp';

    public const EVENT_HEADER = 'X-Webhook-Event';

    public const ID_HEADER = 'X-Webhook-Id';

    public const DELIVERY_HEADER = 'X-Webhook-Delivery';

    public const ATTEMPT_HEADER = 'X-Webhook-Attempt';

    public const SCHEME = [
        'algorithm' => 'HMAC-SHA256',
        'signature_header' => self::SIGNATURE_HEADER,
        'timestamp_header' => self::TIMESTAMP_HEADER,
        'value_format' => 'sha256=<lowercase hex digest>',
        'signed_payload' => '{unix_timestamp}.{raw_request_body}',
        'encoded_secret' => 'the endpoint secret as issued, used verbatim as the HMAC key',
        'replay_window_seconds' => self::TOLERANCE_SECONDS,
        'verification_steps' => [
            '1. Read the raw body exactly as received; do not re-encode the JSON.',
            '2. Read the unix timestamp from X-Webhook-Timestamp.',
            '3. Reject the request when abs(now - timestamp) > 300.',
            '4. Rebuild the signed payload as timestamp + "." + raw body.',
            '5. Compute "sha256=" . hash_hmac("sha256", signedPayload, secret).',
            '6. Compare against X-Webhook-Signature with hash_equals().',
        ],
        'php_reference' => <<<'PHP'
        $raw   = file_get_contents('php://input');
        $ts    = $_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'] ?? '';
        $sig   = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';

        if ($ts === '' || abs(time() - (int) $ts) > 300) {
            http_response_code(400);
            exit('stale');
        }

        $expected = 'sha256='.hash_hmac('sha256', $ts.'.'.$raw, $secret);

        if (! hash_equals($expected, $sig)) {
            http_response_code(400);
            exit('bad signature');
        }
        PHP,
        'other_languages' => [
            'node' => "const expected = 'sha256=' + createHmac('sha256', secret).update(ts + '.' + raw).digest('hex');",
            'python' => "expected = 'sha256=' + hmac.new(secret.encode(), (ts + '.' + raw).encode(), hashlib.sha256).hexdigest()",
            'go' => "mac := hmac.New(sha256.New, []byte(secret)); mac.Write([]byte(ts + \".\" + raw)); expected := \"sha256=\" + hex.EncodeToString(mac.Sum(nil))",
        ],
        'response_contract' => 'Answer 2xx once the body is durably accepted. 410 disables the endpoint permanently.',
    ];

    public function __construct(private readonly int $tolerance = self::TOLERANCE_SECONDS) {}

    public function timestamp(?int $at = null): int
    {
        return $at ?? now()->getTimestamp();
    }

    public function signedPayload(string $rawBody, int $timestamp): string
    {
        return $timestamp.'.'.$rawBody;
    }

    public function sign(string $rawBody, string $secret, ?int $timestamp = null): string
    {
        return $this->expected($rawBody, $secret, $this->timestamp($timestamp));
    }

    public function expected(string $rawBody, string $secret, int $timestamp): string
    {
        return self::ALGORITHM.'='.hash_hmac(self::ALGORITHM, $this->signedPayload($rawBody, $timestamp), $secret);
    }

    /**
     * @param  array<string, string>  $extra
     * @return array<string, string>
     */
    public function headers(string $rawBody, string $secret, array $extra = [], ?int $timestamp = null): array
    {
        $timestamp = $this->timestamp($timestamp);

        return array_merge($extra, [
            self::SIGNATURE_HEADER => $this->expected($rawBody, $secret, $timestamp),
            self::TIMESTAMP_HEADER => (string) $timestamp,
        ]);
    }

    public function verify(
        string $rawBody,
        string $secret,
        ?string $signature,
        ?string $timestamp,
        ?int $now = null,
    ): bool {
        if ($signature === null || $timestamp === null || $timestamp === '' || ! ctype_digit($timestamp)) {
            return false;
        }

        $timestamp = (int) $timestamp;
        $now ??= now()->getTimestamp();

        if (abs($now - $timestamp) > $this->tolerance) {
            return false;
        }

        return hash_equals($this->expected($rawBody, $secret, $timestamp), $signature);
    }

    public function uuid(): string
    {
        return (string) Str::uuid();
    }
}
