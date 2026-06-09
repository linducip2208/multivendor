<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function index()
    {
        $tickets = SupportTicket::where('customer_id', auth()->id())->latest()->paginate(10);
        return view('storefront.tickets.index', compact('tickets'));
    }

    public function create()
    {
        return view('storefront.tickets.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'type' => 'required|in:order,product,payment,account,other',
            'priority' => 'required|in:low,medium,high,urgent',
            'description' => 'required|string',
        ]);
        $validated['customer_id'] = auth()->id();
        $validated['status'] = 'open';
        SupportTicket::create($validated);
        return redirect()->route('tickets.index')->with('success', 'Tiket berhasil dibuat.');
    }

    public function show(SupportTicket $ticket)
    {
        if ($ticket->customer_id !== auth()->id()) abort(403);
        $ticket->load('replies.user');
        return view('storefront.tickets.show', compact('ticket'));
    }

    public function reply(Request $request, SupportTicket $ticket)
    {
        if ($ticket->customer_id !== auth()->id()) abort(403);
        $request->validate(['message' => 'required|string']);
        SupportTicketReply::create(['support_ticket_id' => $ticket->id, 'user_id' => auth()->id(), 'message' => $request->message]);
        return back()->with('success', 'Balasan dikirim.');
    }
}
