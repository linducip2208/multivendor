<?php

declare(strict_types=1);

namespace App\Payments;

use App\Payments\Providers\IpaymuGateway;
use App\Payments\Providers\MidtransGateway;
use App\Payments\Providers\TripayGateway;
use App\Payments\Providers\XenditGateway;

/**
 * Katalog metadata provider Indonesia / Indonesian provider catalog.
 *
 * Sumber metadata statis untuk admin/integrator (pilihan gateway, batas
 * nominal, estimasi biaya, prioritas, dukungan mode uji). TANPA kredensial
 * dalam bentuk apa pun — hanya kode + batas + kemampuan publik.
 *
 * @see GatewayRegistry
 */
final class ProviderCatalog
{
    /**
     * @var array<string, class-string<PaymentGatewayInterface>>
     */
    private const CLASSES = [
        MidtransGateway::CODE => MidtransGateway::class,
        XenditGateway::CODE => XenditGateway::class,
        TripayGateway::CODE => TripayGateway::class,
        IpaymuGateway::CODE => IpaymuGateway::class,
    ];

    /**
     * @return array<int, array{code:string,name:string,api_format:string,countries:string[],currencies:string[],methods:string[],min_amount:float,max_amount:float,fee_percent:float,fee_flat:float,priority:int,test_mode_supported:bool}>
     */
    public static function all(): array
    {
        return [
            [
                'code' => MidtransGateway::CODE,
                'name' => 'Midtrans',
                'api_format' => 'midtrans-snap',
                'countries' => ['ID'],
                'currencies' => ['IDR'],
                'methods' => [
                    PaymentMethod::VIRTUAL_ACCOUNT,
                    PaymentMethod::BANK_TRANSFER,
                    PaymentMethod::E_WALLET,
                    PaymentMethod::QRIS,
                    PaymentMethod::CREDIT_CARD,
                    PaymentMethod::RETAIL_OUTLET,
                ],
                'min_amount' => 100.0,
                'max_amount' => 100000000.0,
                'fee_percent' => 2.9,
                'fee_flat' => 2000.0,
                'priority' => 10,
                'test_mode_supported' => true,
            ],
            [
                'code' => XenditGateway::CODE,
                'name' => 'Xendit',
                'api_format' => 'xendit-invoice',
                'countries' => ['ID'],
                'currencies' => ['IDR'],
                'methods' => [
                    PaymentMethod::VIRTUAL_ACCOUNT,
                    PaymentMethod::QRIS,
                    PaymentMethod::E_WALLET,
                    PaymentMethod::RETAIL_OUTLET,
                ],
                'min_amount' => 10000.0,
                'max_amount' => 1000000000.0,
                'fee_percent' => 0.0,
                'fee_flat' => 4000.0,
                'priority' => 20,
                'test_mode_supported' => true,
            ],
            [
                'code' => TripayGateway::CODE,
                'name' => 'Tripay',
                'api_format' => 'tripay-closed',
                'countries' => ['ID'],
                'currencies' => ['IDR'],
                'methods' => [
                    PaymentMethod::VIRTUAL_ACCOUNT,
                    PaymentMethod::E_WALLET,
                    PaymentMethod::QRIS,
                ],
                'min_amount' => 1000.0,
                'max_amount' => 10000000.0,
                'fee_percent' => 0.0,
                'fee_flat' => 4000.0,
                'priority' => 30,
                'test_mode_supported' => true,
            ],
            [
                'code' => IpaymuGateway::CODE,
                'name' => 'iPaymu',
                'api_format' => 'ipaymu-api',
                'countries' => ['ID'],
                'currencies' => ['IDR'],
                'methods' => [
                    PaymentMethod::VIRTUAL_ACCOUNT,
                    PaymentMethod::BANK_TRANSFER,
                    PaymentMethod::E_WALLET,
                    PaymentMethod::QRIS,
                    PaymentMethod::RETAIL_OUTLET,
                    PaymentMethod::CREDIT_CARD,
                ],
                'min_amount' => 1000.0,
                'max_amount' => 100000000.0,
                'fee_percent' => 2.0,
                'fee_flat' => 0.0,
                'priority' => 40,
                'test_mode_supported' => true,
            ],
        ];
    }

    /** @return array{code:string,name:string,api_format:string,countries:string[],currencies:string[],methods:string[],min_amount:float,max_amount:float,fee_percent:float,fee_flat:float,priority:int,test_mode_supported:bool}|null */
    public static function find(string $code): ?array
    {
        foreach (self::all() as $entry) {
            if (strtolower($entry['code']) === strtolower($code)) {
                return $entry;
            }
        }

        return null;
    }

    /** @return string[] */
    public static function codes(): array
    {
        return array_map(static fn (array $entry): string => $entry['code'], self::all());
    }

    /**
     * Daftarkan keempat gateway Indonesia ke registry beserta kapabilitasnya.
     * Pabrik memakai konstruktor tanpa argumen (mode stub, tanpa jaringan)
     * sehingga aman dipakai di test maupun discovery.
     */
    public static function registerAll(GatewayRegistry $registry): GatewayRegistry
    {
        foreach (self::all() as $entry) {
            $class = self::CLASSES[$entry['code']] ?? null;
            if ($class === null) {
                continue;
            }
            $registry->register($entry['code'], static fn (): PaymentGatewayInterface => new $class(), [
                'currencies' => $entry['currencies'],
                'countries' => $entry['countries'],
                'methods' => $entry['methods'],
            ]);
        }

        return $registry;
    }
}
