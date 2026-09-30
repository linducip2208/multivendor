<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use App\Models\TaxRule;
use Illuminate\Database\Seeder;

/**
 * Seed geo + pajak database-driven (file BARU, tidak menyentuh seeder existing).
 *
 * Catatan tarif umum saat penulisan:
 * - ID PPN standar 11% inclusive (harga konsumen umumnya sudah termasuk PPN;
 *   tarif 12% hanya untuk barang mewah / luxury class — diwakili rule terpisah).
 * - US 0% base di level federal (pajak riil per state/county di dunia nyata;
 *   sampel memakai 0% agar fallback netral teruji).
 * - EU sampel: DE 19% exclusive, FR 20% exclusive (standard rating masing-masing).
 * - Asia sampel: SG 9% exclusive (GST), MY 10% exclusive (SST sales),
 *   JP 10% inclusive (consumption tax, harga display termasuk pajak).
 */
class GeoSeeder extends Seeder
{
    public function run(): void
    {
        $countries = [
            [
                'iso2' => 'ID', 'iso3' => 'IDN', 'name' => 'Indonesia',
                'currency_code' => 'IDR', 'locale' => 'id', 'timezone' => 'Asia/Jakarta',
                'payment_hints' => ['qris', 'bank_transfer', 'cod', 'e_wallet'],
                'shipping_hints' => ['jne', 'jnt', 'sicepat', 'pos'],
                'regions' => [
                    ['code' => 'JK', 'name' => 'DKI Jakarta', 'type' => 'province', 'cities' => [
                        ['name' => 'Jakarta Selatan', 'code' => 'JKT-SEL'],
                        ['name' => 'Jakarta Timur', 'code' => 'JKT-TIM'],
                    ]],
                    ['code' => 'JB', 'name' => 'Jawa Barat', 'type' => 'province', 'cities' => [
                        ['name' => 'Bandung', 'code' => 'BDG'],
                        ['name' => 'Bogor', 'code' => 'BGR'],
                    ]],
                ],
                'taxes' => [
                    // PPN standar 11% inclusive; luxury 12% exclusive sebagai kelas terpisah.
                    ['tax_class' => 'standard', 'rate' => 11.0, 'is_inclusive' => true, 'is_compound' => false, 'priority' => 0, 'region_code' => null],
                    ['tax_class' => 'luxury', 'rate' => 12.0, 'is_inclusive' => false, 'is_compound' => false, 'priority' => 0, 'region_code' => null],
                ],
            ],
            [
                'iso2' => 'US', 'iso3' => 'USA', 'name' => 'United States',
                'currency_code' => 'USD', 'locale' => 'en_US', 'timezone' => 'America/New_York',
                'payment_hints' => ['card', 'paypal', 'ach'],
                'shipping_hints' => ['usps', 'ups', 'fedex'],
                'regions' => [
                    ['code' => 'CA', 'name' => 'California', 'type' => 'state', 'cities' => [
                        ['name' => 'Los Angeles', 'code' => 'LA'],
                    ]],
                    ['code' => 'NY', 'name' => 'New York', 'type' => 'state', 'cities' => [
                        ['name' => 'New York City', 'code' => 'NYC'],
                    ]],
                ],
                'taxes' => [
                    ['tax_class' => 'standard', 'rate' => 0.0, 'is_inclusive' => false, 'is_compound' => false, 'priority' => 0, 'region_code' => null],
                ],
            ],
            [
                'iso2' => 'DE', 'iso3' => 'DEU', 'name' => 'Germany',
                'currency_code' => 'EUR', 'locale' => 'de_DE', 'timezone' => 'Europe/Berlin',
                'payment_hints' => ['sepa', 'card', 'paypal'],
                'shipping_hints' => ['dhl', 'hermes', 'ups'],
                'regions' => [
                    ['code' => 'BY', 'name' => 'Bavaria', 'type' => 'state', 'cities' => [
                        ['name' => 'Munich', 'code' => 'MUC'],
                    ]],
                ],
                'taxes' => [
                    ['tax_class' => 'standard', 'rate' => 19.0, 'is_inclusive' => false, 'is_compound' => false, 'priority' => 0, 'region_code' => null],
                    ['tax_class' => 'reduced', 'rate' => 7.0, 'is_inclusive' => false, 'is_compound' => false, 'priority' => 0, 'region_code' => null],
                ],
            ],
            [
                'iso2' => 'FR', 'iso3' => 'FRA', 'name' => 'France',
                'currency_code' => 'EUR', 'locale' => 'fr_FR', 'timezone' => 'Europe/Paris',
                'payment_hints' => ['sepa', 'card', 'paypal'],
                'shipping_hints' => ['colissimo', 'chronopost', 'dhl'],
                'regions' => [
                    ['code' => 'IDF', 'name' => 'Ile-de-France', 'type' => 'region', 'cities' => [
                        ['name' => 'Paris', 'code' => 'PAR'],
                    ]],
                ],
                'taxes' => [
                    ['tax_class' => 'standard', 'rate' => 20.0, 'is_inclusive' => false, 'is_compound' => false, 'priority' => 0, 'region_code' => null],
                    ['tax_class' => 'reduced', 'rate' => 5.5, 'is_inclusive' => false, 'is_compound' => false, 'priority' => 0, 'region_code' => null],
                ],
            ],
            [
                'iso2' => 'SG', 'iso3' => 'SGP', 'name' => 'Singapore',
                'currency_code' => 'SGD', 'locale' => 'en_SG', 'timezone' => 'Asia/Singapore',
                'payment_hints' => ['card', 'paynow', 'grabpay'],
                'shipping_hints' => ['singpost', 'ninja_van', 'dhl'],
                'regions' => [
                    ['code' => 'CTR', 'name' => 'Central Region', 'type' => 'region', 'cities' => [
                        ['name' => 'Singapore City', 'code' => 'SGP-CITY'],
                    ]],
                ],
                'taxes' => [
                    ['tax_class' => 'standard', 'rate' => 9.0, 'is_inclusive' => false, 'is_compound' => false, 'priority' => 0, 'region_code' => null],
                ],
            ],
            [
                'iso2' => 'MY', 'iso3' => 'MYS', 'name' => 'Malaysia',
                'currency_code' => 'MYR', 'locale' => 'ms_MY', 'timezone' => 'Asia/Kuala_Lumpur',
                'payment_hints' => ['fpx', 'card', 'e_wallet'],
                'shipping_hints' => ['pos_laju', 'jnt', 'dhl'],
                'regions' => [
                    ['code' => 'KUL', 'name' => 'Kuala Lumpur', 'type' => 'federal_territory', 'cities' => [
                        ['name' => 'Kuala Lumpur', 'code' => 'KUL-CITY'],
                    ]],
                ],
                'taxes' => [
                    ['tax_class' => 'standard', 'rate' => 10.0, 'is_inclusive' => false, 'is_compound' => false, 'priority' => 0, 'region_code' => null],
                ],
            ],
            [
                'iso2' => 'JP', 'iso3' => 'JPN', 'name' => 'Japan',
                'currency_code' => 'JPY', 'locale' => 'ja_JP', 'timezone' => 'Asia/Tokyo',
                'payment_hints' => ['card', 'konbini', 'bank_transfer'],
                'shipping_hints' => ['yamato', 'sagawa', 'japan_post'],
                'regions' => [
                    ['code' => 'TK', 'name' => 'Tokyo', 'type' => 'prefecture', 'cities' => [
                        ['name' => 'Shinjuku', 'code' => 'TYO-SJK'],
                    ]],
                ],
                'taxes' => [
                    ['tax_class' => 'standard', 'rate' => 10.0, 'is_inclusive' => true, 'is_compound' => false, 'priority' => 0, 'region_code' => null],
                ],
            ],
        ];

        foreach ($countries as $data) {
            $country = Country::updateOrCreate(
                ['iso2' => $data['iso2']],
                [
                    'iso3' => $data['iso3'],
                    'name' => $data['name'],
                    'currency_code' => $data['currency_code'],
                    'locale' => $data['locale'],
                    'timezone' => $data['timezone'],
                    'is_active' => true,
                    'payment_hints' => $data['payment_hints'],
                    'shipping_hints' => $data['shipping_hints'],
                ],
            );

            $regionIds = [];
            foreach ($data['regions'] as $regionData) {
                $region = Region::updateOrCreate(
                    ['country_id' => $country->id, 'code' => $regionData['code']],
                    ['name' => $regionData['name'], 'type' => $regionData['type'], 'is_active' => true],
                );
                $regionIds[$regionData['code']] = $region->id;

                foreach ($regionData['cities'] as $cityData) {
                    City::updateOrCreate(
                        ['region_id' => $region->id, 'name' => $cityData['name']],
                        ['country_id' => $country->id, 'code' => $cityData['code'], 'is_active' => true],
                    );
                }
            }

            foreach ($data['taxes'] as $tax) {
                TaxRule::updateOrCreate(
                    [
                        'country_id' => $country->id,
                        'region_id' => $tax['region_code'] ? ($regionIds[$tax['region_code']] ?? null) : null,
                        'tax_class' => $tax['tax_class'],
                    ],
                    [
                        'rate' => $tax['rate'],
                        'is_inclusive' => $tax['is_inclusive'],
                        'is_compound' => $tax['is_compound'],
                        'priority' => $tax['priority'],
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
