<?php

declare(strict_types=1);

namespace App\Services\Vendor;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Vendor support tickets.
 *
 * The pre-existing `support_tickets` table is customer-facing and has no shop
 * column, so vendor tickets are proven by the vendor's own user id and the
 * shop column added by the vendor operating tables migration.
 */
final class VendorTicketService
{
    public const CATEGORIES = [
        'order' => 'Pesanan',
        'payment' => 'Pembayaran',
        'product' => 'Produk',
        'shipping' => 'Pengiriman',
        'account' => 'Akun',
        'technical' => 'Teknis',
        'other' => 'Lainnya',
    ];

    public const PRIORITIES = [
        'low' => 'Rendah',
        'normal' => 'Normal',
        'high' => 'Tinggi',
        'urgent' => 'Mendesak',
    ];

    public const STATUSES = [
        'open' => 'Terbuka',
        'pending' => 'Menunggu',
        'answered' => 'Dijawab',
        'resolved' => 'Selesai',
        'closed' => 'Ditutup',
    ];

    public function __construct(private readonly VendorScope $scope) {}

    public function index(string $status = ''): array
    {
        $shopId = $this->scope->shopId();

        $query = DB::table('support_tickets')
            ->where('shop_id', $shopId)
            ->when(in_array($status, array_keys(self::STATUSES), true), fn ($q) => $q->where('status', $status))
            ->orderByDesc('updated_at')
            ->paginate(20)
            ->withQueryString();

        $counts = DB::table('support_tickets')
            ->where('shop_id', $shopId)
            ->select('status', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $out = [];

        foreach (self::STATUSES as $key => $label) {
            $out[$key] = ['label' => $label, 'total' => (int) ($counts[$key] ?? 0)];
        }

        return [
            'tickets' => $query,
            'statuses' => $out,
            'categories' => self::CATEGORIES,
            'priorities' => self::PRIORITIES,
            'selected' => $status,
            'open' => (int) ($counts['open'] ?? 0),
        ];
    }

    public function show(int $ticketId): array
    {
        $ticket = $this->find($ticketId);

        $replies = DB::table('support_ticket_replies')
            ->where('support_ticket_id', $ticket->id)
            ->leftJoin('users', 'users.id', '=', 'support_ticket_replies.user_id')
            ->select([
                'support_ticket_replies.id',
                'support_ticket_replies.body',
                'support_ticket_replies.created_at',
                'users.name as author',
                'users.role as author_role',
            ])
            ->orderBy('support_ticket_replies.created_at')
            ->get();

        return [
            'ticket' => $ticket,
            'replies' => $replies,
            'statuses' => self::STATUSES,
        ];
    }

    public function store(array $payload): int
    {
        $shopId = $this->scope->shopId();

        return DB::transaction(function () use ($shopId, $payload): int {
            $id = DB::table('support_tickets')->insertGetId([
                'shop_id' => $shopId,
                'vendor_id' => $this->scope->userId(),
                'customer_id' => $this->scope->userId(),
                'reference' => $this->reference(),
                'subject' => VendorScope::clean($payload['subject'], 160),
                'type' => in_array($payload['category'] ?? 'other', array_keys(self::CATEGORIES), true) ? $payload['category'] : 'other',
                'description' => VendorScope::clean($payload['message'], 4000),
                'priority' => in_array($payload['priority'] ?? 'normal', array_keys(self::PRIORITIES), true) ? $payload['priority'] : 'normal',
                'status' => 'open',
                'order_id' => ! empty($payload['order_id']) ? (int) $payload['order_id'] : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('support_ticket_replies')->insert([
                'support_ticket_id' => $id,
                'user_id' => $this->scope->userId(),
                'message' => VendorScope::clean($payload['message'], 4000),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $id;
        }, 3);
    }

    public function reply(int $ticketId, string $body): void
    {
        $ticket = $this->find($ticketId);

        $body = VendorScope::clean($body, 4000);

        if ($body === '') {
            throw \Illuminate\Validation\ValidationException::withMessages(['body' => 'Balasan tidak boleh kosong.']);
        }

        DB::transaction(function () use ($ticket, $body): void {
            DB::table('support_ticket_replies')->insert([
                'support_ticket_id' => $ticket->id,
                'user_id' => $this->scope->userId(),
                'message' => $body,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('support_tickets')->where('id', $ticket->id)->update([
                'status' => 'pending',
                'updated_at' => now(),
            ]);
        }, 3);
    }

    public function close(int $ticketId): void
    {
        $ticket = $this->find($ticketId);

        DB::table('support_tickets')->where('id', $ticket->id)->update([
            'status' => 'closed',
            'resolved_at' => now(),
            'closed_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function find(int $ticketId): object
    {
        $ticket = DB::table('support_tickets')
            ->where('shop_id', $this->scope->shopId())
            ->where('id', $ticketId)
            ->first();

        abort_if($ticket === null, 404);

        return $ticket;
    }

    private function reference(): string
    {
        do {
            $reference = 'TKT-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (DB::table('support_tickets')->where('reference', $reference)->exists());

        return $reference;
    }
}
