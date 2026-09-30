<?php

declare(strict_types=1);

namespace App\Services\Geo;

use App\Models\Country;
use App\Models\Region;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Mesin pajak database-driven: seluruh tarif & perilaku berasal dari
 * tabel `tax_rules`. TIDAK ada hardcode tarif negara mana pun di sini.
 *
 * Mode:
 * - exclusive: $amount = net, pajak ditambahkan di atasnya.
 * - inclusive: $amount = gross (sudah termasuk pajak), pajak diekstrak.
 * - none: tidak ada rule aktif → pajak 0 (fallback aman).
 * - mixed: rule inclusive diekstrak dulu, lalu rule exclusive ditambahkan.
 *
 * Compound exclusive: basis = net + akumulasi pajak sebelumnya.
 * Compound inclusive: pengelupasan berurutan dari prioritas terakhir.
 */
class TaxEngine
{
    /**
     * @return Collection<int,\App\Models\TaxRule>
     */
    public function rulesFor(Country $country, ?Region $region = null, string $taxClass = 'standard'): Collection
    {
        if (! Schema::hasTable('tax_rules')) {
            return collect();
        }

        return $country->taxRules()
            ->where('tax_class', $taxClass)
            ->where('is_active', true)
            ->where(function ($query) use ($region) {
                $query->whereNull('region_id');
                if ($region !== null) {
                    $query->orWhere('region_id', $region->getKey());
                }
            })
            ->orderBy('priority')
            ->orderBy('id')
            ->get();
    }

    /**
     * Hitung pajak via ISO negara + kode region (DB-driven, aman fallback).
     *
     * @return array{net:float,tax:float,gross:float,mode:string,country:?string,region:?string,tax_class:string,breakdown:list<array{rule_id:int,tax_class:string,rate:float,is_inclusive:bool,is_compound:bool,tax_amount:float}>}
     */
    public function calculateByIso(
        float $amount,
        ?string $countryIso,
        ?string $regionCode = null,
        string $taxClass = 'standard',
    ): array {
        $country = null;
        $region = null;

        try {
            if (Schema::hasTable('countries') && $countryIso) {
                $country = Country::query()
                    ->where('iso2', strtoupper(trim($countryIso)))
                    ->where('is_active', true)
                    ->first();
            }
        } catch (\Throwable) {
            $country = null;
        }

        if ($country === null) {
            return $this->emptyQuote($amount, $taxClass, $countryIso, $regionCode);
        }

        if ($regionCode !== null && Schema::hasTable('regions')) {
            $region = $country->regions()
                ->where('code', strtoupper(trim($regionCode)))
                ->where('is_active', true)
                ->first();
        }

        return $this->calculate($amount, $country, $region, $taxClass);
    }

    /**
     * @return array{net:float,tax:float,gross:float,mode:string,country:?string,region:?string,tax_class:string,breakdown:list<array{rule_id:int,tax_class:string,rate:float,is_inclusive:bool,is_compound:bool,tax_amount:float}>}
     */
    public function calculate(
        float $amount,
        Country $country,
        ?Region $region = null,
        string $taxClass = 'standard',
    ): array {
        $amount = max(0.0, $amount);
        $rules = $this->rulesFor($country, $region, $taxClass)->values();

        if ($rules->isEmpty()) {
            return $this->emptyQuote($amount, $taxClass, $country->iso2, $region?->code);
        }

        [$inclusive, $exclusive] = [
            $rules->where('is_inclusive', true)->values(),
            $rules->where('is_inclusive', false)->values(),
        ];

        // 1) Ekstrak porsi inclusive (gross → net).
        $net = $amount;
        $breakdown = [];

        if ($inclusive->isNotEmpty()) {
            $extracted = $this->extractInclusive($amount, $inclusive);
            $net = $extracted['net'];
            $breakdown = $extracted['breakdown'];
        }

        // 2) Tambahkan porsi exclusive (net → gross).
        $mode = match (true) {
            $inclusive->isNotEmpty() && $exclusive->isNotEmpty() => 'mixed',
            $inclusive->isNotEmpty() => 'inclusive',
            default => 'exclusive',
        };

        $exclusiveTax = 0.0;
        if ($exclusive->isNotEmpty()) {
            $added = $this->applyExclusive($net, $exclusive);
            $exclusiveTax = $added['tax'];
            $breakdown = [...$breakdown, ...$added['breakdown']];
        }

        $inclusiveTax = array_sum(array_column($breakdown, 'tax_amount')) - $exclusiveTax;
        $tax = round(array_sum(array_column($breakdown, 'tax_amount')), 2);
        $net = round($net, 2);
        $gross = round($net + ($mode === 'inclusive' ? $inclusiveTax : $tax), 2);

        // Mode exclusive/mixed: gross = net + semua pajak.
        if ($mode !== 'inclusive') {
            $gross = round($net + $tax, 2);
        }

        return [
            'net' => $net,
            'tax' => $tax,
            'gross' => $gross,
            'mode' => $mode,
            'country' => $country->iso2,
            'region' => $region?->code,
            'tax_class' => $taxClass,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * @param  Collection<int,\App\Models\TaxRule>  $rules  semua inclusive, urut prioritas
     * @return array{net:float,breakdown:list<array{rule_id:int,tax_class:string,rate:float,is_inclusive:bool,is_compound:bool,tax_amount:float}>}
     */
    public function extractInclusive(float $gross, Collection $rules): array
    {
        $remainder = $gross;
        $taxes = [];

        // Kelupas dari prioritas terakhir agar compound berlapis benar.
        foreach ($rules->sortByDesc('priority')->sortByDesc('id')->values() as $rule) {
            $rate = max(0.0, (float) $rule->rate);
            if ($rate <= 0) {
                $taxes[] = $this->row($rule, 0.0);

                continue;
            }
            $tax = $remainder - ($remainder / (1 + $rate / 100));
            $tax = round($tax, 2);
            $remainder = round($remainder - $tax, 2);
            $taxes[] = $this->row($rule, $tax);
        }

        return ['net' => round($remainder, 2), 'breakdown' => array_reverse($taxes)];
    }

    /**
     * @param  Collection<int,\App\Models\TaxRule>  $rules  semua exclusive, urut prioritas
     * @return array{tax:float,breakdown:list<array{rule_id:int,tax_class:string,rate:float,is_inclusive:bool,is_compound:bool,tax_amount:float}>}
     */
    public function applyExclusive(float $net, Collection $rules): array
    {
        $accumulated = 0.0;
        $breakdown = [];

        foreach ($rules->sortBy('priority')->sortBy('id')->values() as $rule) {
            $rate = max(0.0, (float) $rule->rate);
            $base = $rule->is_compound ? $net + $accumulated : $net;
            $tax = round($base * $rate / 100, 2);
            $accumulated = round($accumulated + $tax, 2);
            $breakdown[] = $this->row($rule, $tax);
        }

        return ['tax' => $accumulated, 'breakdown' => $breakdown];
    }

    /** @return array{rule_id:int,tax_class:string,rate:float,is_inclusive:bool,is_compound:bool,tax_amount:float} */
    private function row(object $rule, float $tax): array
    {
        return [
            'rule_id' => (int) $rule->getKey(),
            'tax_class' => (string) $rule->tax_class,
            'rate' => (float) $rule->rate,
            'is_inclusive' => (bool) $rule->is_inclusive,
            'is_compound' => (bool) $rule->is_compound,
            'tax_amount' => $tax,
        ];
    }

    /** @return array{net:float,tax:float,gross:float,mode:string,country:?string,region:?string,tax_class:string,breakdown:list<never>} */
    private function emptyQuote(float $amount, string $taxClass, ?string $iso2, ?string $regionCode): array
    {
        $amount = round(max(0.0, $amount), 2);

        return [
            'net' => $amount,
            'tax' => 0.0,
            'gross' => $amount,
            'mode' => 'none',
            'country' => $iso2 ? strtoupper($iso2) : null,
            'region' => $regionCode ? strtoupper($regionCode) : null,
            'tax_class' => $taxClass,
            'breakdown' => [],
        ];
    }
}
