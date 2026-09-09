<?php

namespace App\Services;

use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefundWorkflowService
{
    public function request(OrderItem $item, int $customerId, string $reason): OrderItem
    {
        return DB::transaction(function () use ($item, $customerId, $reason) {
            $item = OrderItem::with('order')->lockForUpdate()->findOrFail($item->id);
            $order = $item->order;
            if ($order->customer_id !== $customerId) {
                abort(403);
            }
            if ($order->payment_status !== 'paid' || $order->order_status !== 'delivered') {
                throw ValidationException::withMessages(['refund' => 'Refund hanya dapat diminta untuk pesanan yang sudah dibayar dan diterima.']);
            }
            if ($item->refund_status !== 'none') {
                throw ValidationException::withMessages(['refund' => 'Permintaan refund untuk item ini sudah ada.']);
            }

            $item->update([
                'refund_status' => 'requested',
                'refund_reason' => $reason,
                'refund_requested_at' => now(),
            ]);
            $order->statusHistory()->create(['status' => 'refund_requested', 'changed_by' => $customerId, 'note' => 'Permintaan refund untuk item #'.$item->id]);
            app(AuditLogger::class)->log('refund.requested', $item, [], ['reason' => $reason], $customerId);

            return $item->fresh();
        });
    }

    public function decide(OrderItem $item, int $vendorId, string $decision, ?string $note = null): OrderItem
    {
        return DB::transaction(function () use ($item, $vendorId, $decision, $note) {
            $item = OrderItem::with('order.shop')->lockForUpdate()->findOrFail($item->id);
            if ($item->order->shop?->vendor_id !== $vendorId) {
                abort(403);
            }
            if ($item->refund_status !== 'requested' || ! in_array($decision, ['approved', 'rejected'], true)) {
                throw ValidationException::withMessages(['status' => 'Keputusan refund tidak valid atau sudah diproses.']);
            }

            $item->update(['refund_status' => $decision, 'refund_admin_note' => $note, 'refund_decided_at' => now()]);
            $item->order->statusHistory()->create(['status' => "refund_{$decision}", 'changed_by' => $vendorId, 'note' => 'Refund item #'.$item->id.($note ? ': '.$note : '')]);
            app(AuditLogger::class)->log('refund.'.$decision, $item, ['refund_status' => 'requested'], ['note' => $note], $vendorId);

            return $item->fresh();
        });
    }
}
