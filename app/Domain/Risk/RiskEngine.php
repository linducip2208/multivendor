<?php

declare(strict_types=1);

namespace App\Domain\Risk;

/**
 * Mesin risiko rules-based yang ekstensibel (JUJUR: bukan ML).
 *
 * Aturan didaftarkan sebagai callable/string: tiap rule mengembalikan
 * null (bersih) atau ['score'=>int 0-100, 'flag'=>string, 'reason'=>string].
 * Keputusan agregat: skor >= holdThreshold → hold, >= reviewThreshold →
 * review, selain itu allow. Ambang & batas dapat dioverride via $config.
 */
final class RiskEngine
{
    /** @var array<string, callable> */
    private array $rules = [];

    /** @var array<string, mixed> */
    private array $config;

    /** @param array<string,mixed> $config */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'velocity_window_minutes' => 60,
            'velocity_max_orders' => 5,
            'coupon_max_uses_per_customer' => 3,
            'refund_abuse_ratio' => 0.5,
            'refund_abuse_min_count' => 3,
            'payment_fail_max' => 3,
            'payment_fail_window_hours' => 24,
            'review_threshold' => 30,
            'hold_threshold' => 70,
        ], $config);

        $this->register('velocity', [$this, 'velocityRule']);
        $this->register('coupon_abuse', [$this, 'couponAbuseRule']);
        $this->register('refund_abuse', [$this, 'refundAbuseRule']);
        $this->register('payment_failure', [$this, 'paymentFailureRule']);
    }

    public function register(string $name, callable $rule): self
    {
        $this->rules[$name] = $rule;

        return $this;
    }

    /**
     * @param  array<string,mixed>  $context  orders_last_hour, coupon_uses,
     *   refund_count, refund_ratio, payment_fails_24h, ...
     * @return array{decision:string, score:int, flags:list<array<string,mixed>>}
     */
    public function evaluate(array $context): array
    {
        $flags = [];

        foreach ($this->rules as $name => $rule) {
            $hit = $rule($context, $this->config);

            if (is_array($hit)) {
                $flags[] = ['rule' => $name] + $hit;
            }
        }

        $score = min(100, array_sum(array_column($flags, 'score')));

        $decision = match (true) {
            $score >= $this->config['hold_threshold'] => 'hold',
            $score >= $this->config['review_threshold'] => 'review',
            default => 'allow',
        };

        return ['decision' => $decision, 'score' => $score, 'flags' => $flags];
    }

    /** @return array<string,mixed>|null */
    private function velocityRule(array $ctx, array $cfg): ?array
    {
        $count = (int) ($ctx['orders_last_hour'] ?? 0);

        if ($count > (int) $cfg['velocity_max_orders']) {
            return ['score' => 50, 'flag' => 'velocity', 'reason' => "Order/jam {$count} melebihi batas {$cfg['velocity_max_orders']}."];
        }

        return null;
    }

    /** @return array<string,mixed>|null */
    private function couponAbuseRule(array $ctx, array $cfg): ?array
    {
        $uses = (int) ($ctx['coupon_uses'] ?? 0);

        if ($uses > (int) $cfg['coupon_max_uses_per_customer']) {
            return ['score' => 40, 'flag' => 'coupon_abuse', 'reason' => "Kupon dipakai {$uses}x melebihi batas {$cfg['coupon_max_uses_per_customer']}."];
        }

        return null;
    }

    /** @return array<string,mixed>|null */
    private function refundAbuseRule(array $ctx, array $cfg): ?array
    {
        $count = (int) ($ctx['refund_count'] ?? 0);
        $ratio = (float) ($ctx['refund_ratio'] ?? 0);

        if ($count >= (int) $cfg['refund_abuse_min_count'] && $ratio >= (float) $cfg['refund_abuse_ratio']) {
            return ['score' => 45, 'flag' => 'refund_abuse', 'reason' => "Rasio refund {$ratio} dari {$count} order."];
        }

        return null;
    }

    /** @return array<string,mixed>|null */
    private function paymentFailureRule(array $ctx, array $cfg): ?array
    {
        $fails = (int) ($ctx['payment_fails_24h'] ?? 0);

        if ($fails >= (int) $cfg['payment_fail_max']) {
            return ['score' => 35, 'flag' => 'payment_failure', 'reason' => "Gagal bayar {$fails}x dalam 24 jam."];
        }

        return null;
    }
}
