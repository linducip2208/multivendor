<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\CustomerSegment;
use App\Models\ProductReview;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Crm\ConversationService;
use App\Services\Crm\CrmService;
use App\Services\Crm\Customer360Service;
use App\Services\Crm\SegmentationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CrmController extends Controller
{
    public function __construct(
        private readonly Customer360Service $profile,
        private readonly SegmentationService $segments,
        private readonly CrmService $crm,
        private readonly ConversationService $inbox,
    ) {}

    public function reviews(Request $request): View
    {
        $query = ProductReview::query()->with(['product:id,name,slug', 'customer:id,name,email']);

        if (($status = (string) $request->query('status', '')) !== '') {
            $query->where('status', $status === 'approved');
        }

        if (($search = trim((string) $request->query('search', ''))) !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('comment', 'like', '%'.$search.'%')
                    ->orWhereHas('product', fn ($p) => $p->where('name', 'like', '%'.$search.'%'));
            });
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = 20;
        $total = (int) (clone $query)->count();

        $rows = $query->orderByDesc('id')->forPage($page, $perPage)->get()
            ->map(fn (ProductReview $review): array => [
                'id' => (int) $review->id,
                'product' => (string) ($review->product?->name ?? 'Produk dihapus'),
                'customer' => (string) ($review->customer?->name ?? 'Pelanggan dihapus'),
                'email' => (string) ($review->customer?->email ?? ''),
                'rating' => (int) $review->rating,
                'comment' => (string) ($review->comment ?? ''),
                'images' => is_array($review->images) ? count($review->images) : 0,
                'approved' => (bool) $review->status,
                'at' => (string) ($review->created_at?->format('Y-m-d H:i') ?? ''),
            ])
            ->all();

        $base = ProductReview::query();

        return view('admin.reviews.index', [
            'rows' => $rows,
            'counts' => [
                'all' => (int) (clone $base)->count(),
                'pending' => (int) (clone $base)->where('status', false)->count(),
                'approved' => (int) (clone $base)->where('status', true)->count(),
            ],
            'average_rating' => round((float) (clone $base)->where('status', true)->avg('rating'), 2),
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) max(1, (int) ceil($total / $perPage)),
            ],
        ]);
    }

    public function moderateReview(Request $request, ProductReview $review): RedirectResponse
    {
        $validated = $request->validate([
            'approved' => ['required', 'boolean'],
        ]);

        $approved = (bool) $validated['approved'];
        $before = ['status' => (bool) $review->status];

        $review->forceFill(['status' => $approved])->save();

        app(AuditLogger::class)->log(
            $approved ? 'review.approved' : 'review.rejected',
            $review,
            $before,
            ['status' => $approved],
            auth('admin')->id(),
        );

        return back()->with('success', $approved ? 'Ulasan disetujui dan tampil di storefront.' : 'Ulasan disembunyikan dari storefront.');
    }

    public function deleteReview(Request $request, ProductReview $review): RedirectResponse
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $snapshot = ['product_id' => (int) $review->product_id, 'rating' => (int) $review->rating];
        $review->delete();

        app(AuditLogger::class)->log('review.deleted', null, $snapshot, ['reason' => $request->input('reason')], auth('admin')->id());

        return back()->with('success', 'Ulasan dihapus permanen.');
    }

    public function segments(Request $request): View
    {
        return view('admin.segments.index', [
            'rows' => $this->segments->list(),
            'presets' => SegmentationService::presets(),
            'catalogue' => SegmentationService::ruleCatalogue(),
            'types' => SegmentationService::TYPES,
        ]);
    }

    public function storeSegment(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(SegmentationService::TYPES))],
            'preset' => ['nullable', 'string', Rule::in(array_keys(SegmentationService::presets()))],
            'rules' => ['nullable', 'array'],
        ]);

        $rules = (array) ($validated['rules'] ?? []);

        if (($preset = $validated['preset'] ?? null) !== null && $preset !== '') {
            $rules = array_merge(SegmentationService::presets()[$preset]['rules'], $rules);
        }

        $segment = CustomerSegment::create([
            'name' => (string) $validated['name'],
            'slug' => $this->segments->uniqueSlug((string) $validated['name']),
            'description' => $validated['description'] ?? null,
            'type' => (string) $validated['type'],
            'rules' => $rules ?: null,
            'member_count' => 0,
            'is_dynamic' => $validated['type'] === SegmentationService::TYPE_RULE,
        ]);

        if ($segment->is_dynamic) {
            $result = $this->segments->sync($segment);
            $segment->refresh();

            return back()->with('success', 'Segmen dibuat dan '.number_format($result['matched'], 0, ',', '.').' anggota langsung dihitung dari data pesanan.');
        }

        return back()->with('success', 'Segmen manual dibuat. Tambahkan anggota dari halaman detail.');
    }

    public function syncSegment(Request $request, CustomerSegment $segment): RedirectResponse
    {
        if ($segment->type !== SegmentationService::TYPE_RULE) {
            return back()->with('error', 'Hanya segmen berbasis aturan yang dapat dihitung ulang otomatis.');
        }

        $result = $this->segments->sync($segment);

        return back()->with(
            'success',
            'Keanggotaan diperbarui: '.$result['added'].' ditambahkan, '.$result['removed'].' dikeluarkan dari total '.$result['matched'].' anggota.',
        );
    }

    public function destroySegment(CustomerSegment $segment): RedirectResponse
    {
        $snapshot = ['name' => (string) $segment->name, 'members' => (int) $segment->member_count];
        $segment->delete();

        app(AuditLogger::class)->log('segment.deleted', null, $snapshot, [], auth('admin')->id());

        return back()->with('success', 'Segmen dihapus beserta seluruh keanggotaannya.');
    }

    public function loyalty(Request $request): View
    {
        $report = $this->crm->loyaltyOverview(
            (int) $request->query('per_page', 20),
            (int) $request->query('page', 1),
            trim((string) $request->query('search', '')),
        );

        return view('admin.loyalty.index', $report);
    }

    public function show(Request $request, User $user): View
    {
        if ($user->role !== 'customer' && $user->role !== 'delivery') {
            abort(404, 'Pelanggan tidak ditemukan.');
        }

        return view('admin.customers.360', [
            'customer' => $this->profile->profile((int) $user->id),
        ]);
    }

    public function activity(Request $request): View
    {
        $query = \App\Models\CustomerActivity::query()->with('customer:id,name,email');

        if (($customerId = (int) $request->query('customer', 0)) > 0) {
            $query->where('customer_id', $customerId);
        }

        if (($type = trim((string) $request->query('type', ''))) !== '') {
            $query->where('type', $type);
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = 25;
        $total = (int) (clone $query)->count();

        $rows = $query->orderByDesc('id')->forPage($page, $perPage)->get()
            ->map(fn (\App\Models\CustomerActivity $activity): array => [
                'id' => (int) $activity->id,
                'customer_id' => (int) $activity->customer_id,
                'customer' => (string) ($activity->customer?->name ?? 'Pelanggan dihapus'),
                'email' => (string) ($activity->customer?->email ?? ''),
                'type' => (string) $activity->type,
                'description' => (string) ($activity->description ?? ''),
                'ip_address' => (string) ($activity->ip_address ?? ''),
                'at' => (string) ($activity->created_at?->format('Y-m-d H:i:s') ?? ''),
                'url' => route('admin.customers.360', $activity->customer_id),
            ])
            ->all();

        $types = \App\Models\CustomerActivity::query()->distinct()->orderBy('type')->pluck('type')
            ->map(fn ($type): array => ['value' => (string) $type, 'label' => \Illuminate\Support\Str::headline((string) $type)])
            ->all();

        return view('admin.activity-log', [
            'rows' => $rows,
            'types' => $types,
            'selected_type' => (string) $request->query('type', ''),
            'selected_customer' => (int) $request->query('customer', 0),
            'total_events' => (int) \App\Models\CustomerActivity::query()->count(),
            'today_events' => (int) \App\Models\CustomerActivity::query()->whereDate('created_at', today())->count(),
            'unique_customers' => (int) \App\Models\CustomerActivity::query()->distinct()->count('customer_id'),
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) max(1, (int) ceil($total / $perPage)),
            ],
        ]);
    }

    public function wishlists(Request $request): View
    {
        return view('admin.wishlists', $this->crm->wishlistOverview(
            (int) $request->query('per_page', 25),
            (int) $request->query('page', 1),
            trim((string) $request->query('search', '')),
        ));
    }

    public function conversations(Request $request): View
    {
        return view('admin.conversations.index', $this->inbox->index(
            (int) $request->query('page', 1),
            trim((string) $request->query('search', '')),
            (string) $request->query('status', ''),
        ));
    }

    public function conversation(Request $request, Conversation $conversation): View
    {
        return view('admin.conversations.show', $this->inbox->show($conversation, auth('admin')->id()));
    }

    public function replyConversation(Request $request, Conversation $conversation): RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:5000'],
            'internal_note' => ['nullable', 'boolean'],
            'close' => ['nullable', 'boolean'],
        ]);

        $internal = $request->boolean('internal_note');

        $this->inbox->reply(
            $conversation,
            (string) $validated['body'],
            $internal,
            auth('admin')->id(),
        );

        if ($request->boolean('close')) {
            $conversation->forceFill(['status' => 'closed', 'resolved_at' => now()])->save();
        }

        return back()->with(
            'success',
            $internal ? 'Catatan internal disimpan dan tidak terlihat pelanggan.' : 'Balasan terkirim ke percakapan.',
        );
    }
}
