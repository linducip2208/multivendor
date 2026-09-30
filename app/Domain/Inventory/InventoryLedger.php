<?php

declare(strict_types=1);

namespace App\Domain\Inventory;

/**
 * Snapshot state ledger inventaris per baris stok.
 *
 * Kolom fisik existing: on_hand, reserved, incoming.
 * - available = on_hand − reserved (derivasi, tak pernah negatif di baca).
 * - damaged & returned = state virtual dari arus movement (diinjeksikan
 *   pemanggil dari agregat stock_movements; default 0 bila tak tersedia).
 */
final class InventoryLedger
{
    public const STATES = ['on_hand', 'reserved', 'available', 'damaged', 'returned', 'incoming'];

    /** @return array<string,string> */
    public static function labels(): array
    {
        return [
            'on_hand' => 'Di tangan',
            'reserved' => 'Direservasi',
            'available' => 'Tersedia',
            'damaged' => 'Rusak',
            'returned' => 'Retur',
            'incoming' => 'Dalam perjalanan',
        ];
    }

    /**
     * @param  array{on_hand:int, reserved:int, incoming?:int, damaged?:int, returned?:int}  $row
     * @return array<string,int>
     */
    public static function snapshot(array $row): array
    {
        $onHand = max(0, (int) ($row['on_hand'] ?? 0));
        $reserved = max(0, (int) ($row['reserved'] ?? 0));

        return [
            'on_hand' => $onHand,
            'reserved' => $reserved,
            'available' => max(0, $onHand - $reserved),
            'damaged' => max(0, (int) ($row['damaged'] ?? 0)),
            'returned' => max(0, (int) ($row['returned'] ?? 0)),
            'incoming' => max(0, (int) ($row['incoming'] ?? 0)),
        ];
    }
}
