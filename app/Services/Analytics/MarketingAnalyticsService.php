<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\AbandonedCart;
use App\Models\Affiliate;
use App\Models\AffiliateClick;
use App\Models\Campaign;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\FlashDeal;
use Illuminate\Support\Facades\DB;

/**
 * Marketing effectiveness measured from recorded facts only: campaign clicks and
 * conversions, coupon redemptions, abandoned-cart recovery and affiliate
 * performance. Nothing here infers customer intent.
 */
final class MarketingAnalyticsService extends AnalyticsService
{
    /**
     * @return array<string, mixed>
     */
    public function report(DateRange $range, int $perPage = 20, int $page = 1): array
    {
        return $this->remember('marketing', $range, function () use ($range, $perPage, $page): array {
            $campaigns = Campaign::query()->whereBetween('created_at', [$range->from, $range->to])->count();
            $activeCampaigns = (int) Campaign::query()->active()->count();

            $clicks = (int) Campaign::query()->whereBetween('created_at', [$range->from, $range->to])->sum('clicks');
            $conversions = (int) Campaign::query()->whereBetween('created_at', [$range->from, $range->to])->sum('conversions');
            $revenue = (float) Campaign::query()->whereBetween('created_at', [$range->from, $range->to])->sum('revenue');
            $budget = (float) Campaign::query()->whereBetween('created_at', [$range->from, $range->to])->sum('budget');

            $carts = $this->abandonedCarts($range);
            $affiliates = $this->affiliates($range);
            $coupons = $this->coupons($range);

            return [
                'range' => $range->toArray(),
                'kpis' => [
                    'campaigns' => ['value' => $campaigns, 'label' => 'Kampanye Dibuat', 'money' => false, 'trend' => $this->delta(0.0, 0.0)],
                    'active' => ['value' => $activeCampaigns, 'label' => 'Kampanye Aktif', 'money' => false, 'trend' => $this->delta(0.0, 0.0)],
                    'clicks' => ['value' => $clicks, 'label' => 'Klik', 'money' => false, 'trend' => $this->delta(0.0, 0.0)],
                    'conversions' => ['value' => $conversions, 'label' => 'Konversi', 'money' => false, 'trend' => $this->delta(0.0, 0.0)],
                    'roas' => [
                        'value' => $budget > 0 ? round(($revenue / $budget) * 100, 1) : 0.0,
                        'label' => 'ROAS',
                        'money' => false,
                        'hint' => 'Pendapatan dibagi anggaran kampanye',
                    ],
                    'revenue' => ['value' => $revenue, 'label' => 'Omzet Kampanye', 'money' => true, 'trend' => $this->delta(0.0, 0.0)],
                    'recovery' => ['value' => $carts['recovery_rate'], 'label' => 'Tingkat Pemulihan', 'money' => false, 'hint' => 'Keranjang tertinggal yang kembali menjadi pesanan'],
                ],
                'carts' => $carts,
                'affiliates' => $affiliates,
                'coupons' => $coupons,
                'campaigns' => $this->campaignTable($perPage, $page),
                'flash_deals' => $this->flashDeals(),
            ];
        }, ['per_page' => $perPage, 'page' => $page]);
    }

    /**
     * @return array<string, mixed>
     */
    private function abandonedCarts(DateRange $range): array
    {
        $base = AbandonedCart::query()->whereBetween('created_at', [$range->from, $range->to]);
        $total = (int) (clone $base)->count();
        $value = (float) (clone $base)->sum('amount');
        $recovered = (int) (clone $base)->whereNotNull('recovered_at')->count();
        $reminded = (int) (clone $base)->where('reminder_count', '>', 0)->count();

        return [
            'total' => $total,
            'value' => $value,
            'recovered' => $recovered,
            'reminded' => $reminded,
            'recovery_rate' => $total > 0 ? round(($recovered / $total) * 100, 1) : 0.0,
            'recoverable_value' => $value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function affiliates(DateRange $range): array
    {
        $clicks = (int) AffiliateClick::query()->whereBetween('created_at', [$range->from, $range->to])->count();
        $converted = (int) AffiliateClick::query()->whereBetween('created_at', [$range->from, $range->to])
            ->whereNotNull('converted_order_id')->count();

        $stats = Affiliate::query()
            ->selectRaw('COALESCE(SUM(total_revenue), 0) as revenue, COALESCE(SUM(total_commission), 0) as commission, COUNT(*) as affiliates')
            ->first();

        return [
            'clicks' => $clicks,
            'converted' => $converted,
            'conversion_rate' => $clicks > 0 ? round(($converted / $clicks) * 100, 1) : 0.0,
            'affiliates' => (int) ($stats->affiliates ?? 0),
            'revenue' => (float) ($stats->revenue ?? 0),
            'commission' => (float) ($stats->commission ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function coupons(DateRange $range): array
    {
        $redemptions = (int) CouponUsage::query()->whereBetween('created_at', [$range->from, $range->to])->count();
        $discount = (float) CouponUsage::query()->whereBetween('created_at', [$range->from, $range->to])->sum('discount_amount');

        $top = Coupon::query()
            ->leftJoin('coupon_usages', 'coupon_usages.coupon_id', '=', 'coupons.id')
            ->whereBetween('coupon_usages.created_at', [$range->from, $range->to])
            ->groupBy('coupons.id', 'coupons.code', 'coupons.title')
            ->selectRaw('coupons.id, coupons.code, coupons.title, COUNT(coupon_usages.id) as uses, COALESCE(SUM(coupon_usages.discount_amount), 0) as discount')
            ->orderByDesc('uses')
            ->limit(10)
            ->get()
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'code' => (string) $row->code,
                'title' => (string) $row->title,
                'uses' => (int) $row->uses,
                'discount' => (float) $row->discount,
            ])
            ->all();

        $orders = (int) $this->revenueOrders($range)->count();

        return [
            'redemptions' => $redemptions,
            'discount' => $discount,
            'redemption_rate' => $orders > 0 ? round(($redemptions / $orders) * 100, 1) : 0.0,
            'top' => $top,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function campaignTable(int $perPage, int $page): array
    {
        $perPage = max(5, min(100, $perPage));
        $page = max(1, $page);
        $total = (int) Campaign::query()->count();

        $rows = Campaign::query()
            ->orderByDesc('created_at')
            ->forPage($page, $perPage)
            ->get()
            ->map(function (Campaign $campaign): array {
                $budget = (float) $campaign->budget;
                $revenue = (float) $campaign->revenue;
                $clicks = (int) $campaign->clicks;
                $conversions = (int) $campaign->conversions;

                return [
                    'id' => (int) $campaign->id,
                    'name' => (string) $campaign->name,
                    'type' => (string) $campaign->type,
                    'status' => (string) $campaign->status,
                    'budget' => $budget,
                    'revenue' => $revenue,
                    'clicks' => $clicks,
                    'conversions' => $conversions,
                    'ctr' => $clicks > 0 ? round(($conversions / $clicks) * 100, 2) : 0.0,
                    'roas' => $budget > 0 ? round(($revenue / $budget) * 100, 1) : 0.0,
                    'starts_at' => (string) ($campaign->starts_at?->format('Y-m-d') ?? ''),
                    'ends_at' => (string) ($campaign->ends_at?->format('Y-m-d') ?? ''),
                ];
            })
            ->all();

        return [
            'rows' => $rows,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => (int) max(1, (int) ceil($total / $perPage)),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function flashDeals(): array
    {
        if (! $this->has('flash_deals')) {
            return [];
        }

        return FlashDeal::query()
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn (FlashDeal $deal): array => [
                'id' => (int) $deal->id,
                'title' => (string) $deal->title,
                'status' => (bool) $deal->status,
                'discount' => (int) round($deal->best_discount_percentage),
                'starts_at' => (string) ($deal->start_date?->format('Y-m-d') ?? ''),
                'ends_at' => (string) ($deal->end_date?->format('Y-m-d') ?? ''),
            ])
            ->all();
    }
}
