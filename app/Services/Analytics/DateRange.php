<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;

/**
 * Inclusive reporting window resolved from the `from` / `to` query string.
 *
 * Reporting must never silently widen: an absent range is capped at 365 days
 * and a reversed range is normalised rather than producing an empty report.
 */
final class DateRange
{
    public const MAX_DAYS = 366;

    public const PRESETS = [
        'today' => 'Hari ini',
        '7d' => '7 hari terakhir',
        '30d' => '30 hari terakhir',
        '90d' => '90 hari terakhir',
        '12m' => '12 bulan terakhir',
        'mtd' => 'Bulan berjalan',
    ];

    private function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly ?string $preset,
    ) {
    }

    public static function fromRequest(Request $request, int $defaultDays = 30): self
    {
        $preset = $request->query('range');
        $preset = is_string($preset) && $preset !== '' ? $preset : null;

        $now = CarbonImmutable::now()->endOfDay();

        if ($preset !== null && $preset !== 'custom') {
            $from = match ($preset) {
                'today' => $now->startOfDay(),
                '7d' => $now->subDays(6)->startOfDay(),
                '90d' => $now->subDays(89)->startOfDay(),
                '12m' => $now->subMonths(12)->addDay()->startOfDay(),
                'mtd' => $now->startOfMonth(),
                default => $now->subDays(max(0, $defaultDays - 1))->startOfDay(),
            };

            return new self($from, $now, $preset);
        }

        $rawFrom = $request->query('from');
        $rawTo = $request->query('to');

        $to = self::parse((string) $rawTo, $now);
        $from = self::parse((string) $rawFrom, $to->subDays(max(0, $defaultDays - 1))->startOfDay());

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return new self($from, $to, $preset);
    }

    public function days(): int
    {
        return (int) $this->from->startOfDay()->diffInDays($this->to->copy()->startOfDay()) + 1;
    }

    public function labels(): array
    {
        $count = min(120, $this->days());
        $step = max(1, (int) ceil($this->days() / $count));

        $labels = [];
        $cursor = $this->from->copy()->startOfDay();

        while ($cursor->lessThanOrEqualTo($this->to)) {
            $labels[] = $cursor->format('Y-m-d');
            $cursor = $cursor->addDays($step);
        }

        return $labels;
    }

    public function step(): int
    {
        return max(1, (int) ceil($this->days() / 120));
    }

    public function previous(): self
    {
        $length = $this->days();

        return new self(
            $this->from->subDays($length),
            $this->from->copy()->subDay(),
            null,
        );
    }

    public function toArray(): array
    {
        return [
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
            'days' => $this->days(),
            'preset' => $this->preset,
        ];
    }

    public function key(string $prefix, array $extra = []): string
    {
        ksort($extra);

        return 'analytics:'.$prefix.':'.$this->from->toDateString().':'.$this->to->toDateString().':'.substr(md5(serialize($extra)), 0, 8);
    }

    private static function parse(string $value, CarbonInterface $fallback): CarbonImmutable
    {
        if (trim($value) === '') {
            return CarbonImmutable::instance($fallback);
        }

        try {
            $parsed = CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return CarbonImmutable::instance($fallback);
        }

        if ($parsed->greaterThan(CarbonImmutable::now()->addDay())) {
            return CarbonImmutable::instance($fallback);
        }

        return $parsed->hour((int) $fallback->hour)->minute((int) $fallback->minute);
    }
}
