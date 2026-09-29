<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['customer_id', 'product_id', 'product_variant_id', 'quantity', 'price', 'tax'])]
class Cart extends Model
{
    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'tax' => 'decimal:2'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    // ── Repeat-order langganan (aditif; tabel order_repeat_schedules) ──

    public const REPEAT_FREQUENCIES = ['daily', 'weekly', 'monthly'];

    /**
     * Daftarkan jadwal ulang otomatis. Hanya menyimpan jadwal; eksekusi
     * membuat DRAF cart (bukan order langsung) via runDueRepeats().
     *
     * @param  array{customer_id:int,product_id:int,product_variant_id?:int|null,quantity?:int,frequency?:string,next_run_at?:mixed}  $data
     * @return int id jadwal
     */
    public static function scheduleRepeat(array $data): int
    {
        $frequency = strtolower((string) ($data['frequency'] ?? 'weekly'));

        if (! in_array($frequency, self::REPEAT_FREQUENCIES, true)) {
            throw new \InvalidArgumentException('Frekuensi repeat-order tidak valid.');
        }

        $quantity = max(1, (int) ($data['quantity'] ?? 1));

        return (int) \Illuminate\Support\Facades\DB::table('order_repeat_schedules')->insertGetId([
            'customer_id' => (int) $data['customer_id'],
            'product_id' => (int) $data['product_id'],
            'product_variant_id' => $data['product_variant_id'] ?? null,
            'quantity' => $quantity,
            'frequency' => $frequency,
            'next_run_at' => $data['next_run_at'] ?? self::nextRepeatDate($frequency),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public static function nextRepeatDate(string $frequency, ?\Carbon\CarbonInterface $from = null): \Carbon\CarbonInterface
    {
        $base = $from ? \Carbon\Carbon::parse($from) : now();

        return match ($frequency) {
            'daily' => $base->copy()->addDay(),
            'monthly' => $base->copy()->addMonth(),
            default => $base->copy()->addWeek(),
        };
    }

    /**
     * Eksekusi jadwal jatuh tempo: tiap jadwal dalam transaksinya sendiri
     * (atomicity per jadwal) dengan lockForUpdate; hasilnya draf cart,
     * idempoten terhadap cart existing (tambah kuantitas).
     *
     * @return array{drafts:int,schedules:list<int>}
     */
    public static function runDueRepeats(int $limit = 100): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('order_repeat_schedules')) {
            return ['drafts' => 0, 'schedules' => []];
        }

        $dueIds = \Illuminate\Support\Facades\DB::table('order_repeat_schedules')
            ->where('is_active', true)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now())
            ->orderBy('next_run_at')
            ->limit(max(1, $limit))
            ->pluck('id');

        $drafts = 0;
        $processed = [];

        foreach ($dueIds as $scheduleId) {
            $made = \Illuminate\Support\Facades\DB::transaction(function () use ($scheduleId): bool {
                $schedule = \Illuminate\Support\Facades\DB::table('order_repeat_schedules')
                    ->where('id', $scheduleId)
                    ->lockForUpdate()
                    ->first();

                if (! $schedule || ! $schedule->is_active || $schedule->next_run_at > now()) {
                    return false;
                }

                $product = Product::with('shop')->find($schedule->product_id);

                if (! $product || $product->status !== 'approved' || ! $product->published) {
                    \Illuminate\Support\Facades\DB::table('order_repeat_schedules')
                        ->where('id', $scheduleId)
                        ->update(['last_run_at' => now(), 'updated_at' => now()]);

                    return false;
                }

                $price = $product->getEffectivePrice();

                if ($schedule->product_variant_id) {
                    $variant = ProductVariant::whereKey($schedule->product_variant_id)
                        ->where('product_id', $product->id)
                        ->first();

                    if (! $variant) {
                        return false;
                    }

                    $price = $variant->getEffectivePrice();
                }

                $existing = static::where('customer_id', $schedule->customer_id)
                    ->where('product_id', $schedule->product_id)
                    ->when(
                        $schedule->product_variant_id,
                        fn ($query) => $query->where('product_variant_id', $schedule->product_variant_id),
                        fn ($query) => $query->whereNull('product_variant_id'),
                    )
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    $existing->increment('quantity', (int) $schedule->quantity);
                    $existing->update(['price' => $price]);
                } else {
                    static::create([
                        'customer_id' => $schedule->customer_id,
                        'product_id' => $schedule->product_id,
                        'product_variant_id' => $schedule->product_variant_id,
                        'quantity' => (int) $schedule->quantity,
                        'price' => $price,
                        'tax' => $product->tax,
                    ]);
                }

                \Illuminate\Support\Facades\DB::table('order_repeat_schedules')
                    ->where('id', $scheduleId)
                    ->update([
                        'last_run_at' => now(),
                        'next_run_at' => self::nextRepeatDate((string) $schedule->frequency),
                        'updated_at' => now(),
                    ]);

                return true;
            }, 3);

            if ($made) {
                $drafts++;
                $processed[] = (int) $scheduleId;
            }
        }

        return ['drafts' => $drafts, 'schedules' => $processed];
    }
}
