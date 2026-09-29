<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Http\Request;

class SupportTicketController extends Controller
{
    public function index(Request $request) {
        $query = SupportTicket::with('customer')->latest();
        if($request->filled('status')) $query->where('status',$request->status);
        if($request->filled('priority')) $query->where('priority',$request->priority);
        if($request->boolean('breached')) {
            $query->whereNull('resolved_at')->whereNull('closed_at')->where('created_at', '<', now()->subHours(48));
        }
        $tickets = $query->paginate(15);
        $macros = [
            ['key' => 'minta_detail', 'label' => 'Minta detail', 'body' => 'Mohon lampirkan nomor pesanan dan tangkapan layar kendalanya.'],
            ['key' => 'sedang_diproses', 'label' => 'Sedang diproses', 'body' => 'Laporan Anda sedang kami proses. Estimasi penyelesaian 1x24 jam.'],
            ['key' => 'selesai', 'label' => 'Konfirmasi selesai', 'body' => 'Kendala sudah kami selesaikan. Mohon konfirmasi bila masih ada masalah.'],
        ];
        return view('admin.support-tickets.index',compact('tickets', 'macros'));
    }
    public function show(SupportTicket $ticket) {
        $ticket->load('replies.user');
        app(\App\Services\AuditLogger::class)->impersonate('ticket', (int) $ticket->getKey(), auth('admin')->id(), ['status' => $ticket->status]);

        return view('admin.support-tickets.show', [
            'ticket' => $ticket,
            'sla' => $ticket->sla(),
            'macros' => [
                ['key' => 'minta_detail', 'label' => 'Minta detail', 'body' => 'Mohon lampirkan nomor pesanan dan tangkapan layar kendalanya.'],
                ['key' => 'sedang_diproses', 'label' => 'Sedang diproses', 'body' => 'Laporan Anda sedang kami proses. Estimasi penyelesaian 1x24 jam.'],
                ['key' => 'selesai', 'label' => 'Konfirmasi selesai', 'body' => 'Kendala sudah kami selesaikan. Mohon konfirmasi bila masih ada masalah.'],
            ],
            'duplicates' => SupportTicket::query()->where('id', '!=', $ticket->getKey())->where('subject', $ticket->subject)->where('status', '!=', 'closed')->limit(10)->get(['id', 'reference', 'status']),
        ]);
    }
    public function update(Request $request, SupportTicket $ticket) {
        $before = ['status' => $ticket->status, 'priority' => $ticket->priority, 'assigned_to' => $ticket->assigned_to];
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'max:40'],
            'priority' => ['nullable', 'string', 'max:40'],
            'message' => ['nullable', 'string', 'max:4000'],
            'satisfaction' => ['nullable', 'integer', 'min:1', 'max:5'],
            'escalate' => ['nullable', 'boolean'],
            'merge_ids' => ['nullable', 'array', 'max:20'],
            'merge_ids.*' => ['integer', 'exists:support_tickets,id'],
        ]);
        $updates = ['assigned_to' => auth('admin')->id()];
        if (! empty($validated['status'])) {
            $updates['status'] = $validated['status'];
        }
        if (! empty($validated['priority'])) {
            $updates['priority'] = $validated['priority'];
        }
        if ($request->boolean('escalate')) {
            $updates['priority'] = 'urgent';
            $updates['status'] = $ticket->status === 'closed' ? 'closed' : 'open';
        }
        if (! empty($validated['satisfaction']) && \Illuminate\Support\Facades\Schema::hasColumn('support_tickets', 'satisfaction')) {
            $updates['satisfaction'] = (int) $validated['satisfaction'];
        }
        if (empty($ticket->first_response_at) && ! empty($validated['message'])) {
            $updates['first_response_at'] = now();
        }
        if (($validated['status'] ?? '') === 'resolved' || ($validated['status'] ?? '') === 'closed') {
            $updates['resolved_at'] = $ticket->resolved_at ?? now();
        }
        $ticket->update($updates);
        if(! empty($validated['message'])) {
            $body = $validated['message'];
            $macro = collect([
                'minta_detail' => 'Mohon lampirkan nomor pesanan dan tangkapan layar kendalanya.',
                'sedang_diproses' => 'Laporan Anda sedang kami proses. Estimasi penyelesaian 1x24 jam.',
                'selesai' => 'Kendala sudah kami selesaikan. Mohon konfirmasi bila masih ada masalah.',
            ])->get($validated['message']);
            $ticket->replies()->create(['user_id'=>auth('admin')->id(),'message'=> $macro ?? $body]);
        }
        $merged = 0;
        foreach ((array) ($validated['merge_ids'] ?? []) as $dupId) {
            $dup = SupportTicket::query()->whereKey((int) $dupId)->where('id', '!=', $ticket->getKey())->first();
            if ($dup !== null) {
                \App\Models\SupportTicketReply::query()->where('support_ticket_id', $dup->getKey())->update(['support_ticket_id' => $ticket->getKey()]);
                $dup->forceFill(['status' => 'closed', 'closed_at' => now()])->save();
                $merged++;
            }
        }
        app(\App\Services\AuditLogger::class)->log('ticket.updated', $ticket, $before, [
            'status' => $ticket->fresh()->status,
            'priority' => $ticket->fresh()->priority,
            'escalated' => $request->boolean('escalate'),
            'merged' => $merged,
        ], auth('admin')->id());

        return back()->with('success','Tiket diperbarui.'.($merged > 0 ? ' '.$merged.' duplikat digabung.' : ''));
    }
}
