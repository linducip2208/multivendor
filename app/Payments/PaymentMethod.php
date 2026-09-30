<?php

declare(strict_types=1);

namespace App\Payments;

/**
 * Daftar metode pembayaran baku / Canonical payment methods.
 */
final class PaymentMethod
{
    public const VIRTUAL_ACCOUNT = 'virtual_account';
    public const E_WALLET = 'e_wallet';
    public const QRIS = 'qris';
    public const CREDIT_CARD = 'credit_card';
    public const BANK_TRANSFER = 'bank_transfer';
    public const RETAIL_OUTLET = 'retail_outlet';
    public const DIRECT_DEBIT = 'direct_debit';
    public const PAYLATER = 'paylater';

    /** @return string[] */
    public static function all(): array
    {
        return [
            self::VIRTUAL_ACCOUNT,
            self::E_WALLET,
            self::QRIS,
            self::CREDIT_CARD,
            self::BANK_TRANSFER,
            self::RETAIL_OUTLET,
            self::DIRECT_DEBIT,
            self::PAYLATER,
        ];
    }

    /**
     * Normalisasi alias gateway ("VA", "GoPay", "Credit Card") ke bentuk baku.
     * Unknown values are returned lowercased with spaces converted to underscores.
     */
    public static function normalize(string $method): string
    {
        $key = strtolower(trim($method));

        $aliases = [
            'va' => self::VIRTUAL_ACCOUNT,
            'virtualaccount' => self::VIRTUAL_ACCOUNT,
            'virtual_account' => self::VIRTUAL_ACCOUNT,
            'bank_virtual_account' => self::VIRTUAL_ACCOUNT,
            'e-wallet' => self::E_WALLET,
            'ewallet' => self::E_WALLET,
            'e_wallet' => self::E_WALLET,
            'gopay' => self::E_WALLET,
            'ovo' => self::E_WALLET,
            'dana' => self::E_WALLET,
            'qris' => self::QRIS,
            'qr' => self::QRIS,
            'cc' => self::CREDIT_CARD,
            'creditcard' => self::CREDIT_CARD,
            'credit_card' => self::CREDIT_CARD,
            'card' => self::CREDIT_CARD,
            'transfer' => self::BANK_TRANSFER,
            'bank_transfer' => self::BANK_TRANSFER,
            'retail' => self::RETAIL_OUTLET,
            'retail_outlet' => self::RETAIL_OUTLET,
            'directdebit' => self::DIRECT_DEBIT,
            'direct_debit' => self::DIRECT_DEBIT,
            'paylater' => self::PAYLATER,
            'pay_later' => self::PAYLATER,
        ];

        $flat = str_replace([' ', '-'], ['', ''], $key);
        $flat = str_replace('_', '', $flat);

        foreach ($aliases as $alias => $canonical) {
            if (str_replace('_', '', $alias) === $flat) {
                return $canonical;
            }
        }

        return strtolower(preg_replace('/\s+/', '_', trim($method)) ?? $method);
    }
}
