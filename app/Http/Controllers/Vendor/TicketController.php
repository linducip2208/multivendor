<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Services\Vendor\VendorTicketService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function index(Request $request, VendorTicketService $tickets): View
    {
        $data = $tickets->index(VendorScopeRequest::enum($request, 'status', array_keys(VendorTicketService::STATUSES)));

        return view('vendor.tickets.index', [
            'tickets' => $data['tickets'],
            'statuses' => $data['statuses'],
            'categories' => $data['categories'],
            'priorities' => $data['priorities'],
            'selected' => $data['selected'],
            'open' => $data['open'],
        ]);
    }

    public function show(Request $request, int $ticket, VendorTicketService $service): View
    {
        $data = $service->show($ticket);

        return view('vendor.tickets.show', [
            'ticket' => $data['ticket'],
            'replies' => $data['replies'],
            'statuses' => $data['statuses'],
            'sla' => $service->sla($data['ticket']),
            'macros' => VendorTicketService::macros(),
        ]);
    }

    public function store(Request $request, VendorTicketService $tickets): RedirectResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'min:5', 'max:160'],
            'category' => ['required', 'in:order,payment,product,shipping,account,technical,other'],
            'priority' => ['required', 'in:low,normal,high,urgent'],
            'message' => ['required', 'string', 'min:10', 'max:4000'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
        ], [
            'message.min' => 'Deskripsi minimal 10 karakter agar tim dukungan dapat membantu.',
        ]);

        $id = $tickets->store($validated);

        return redirect()->route('vendor.tickets.show', $id)
            ->with('success', 'Tiket dibuat. Tim kami akan membalas melalui halaman ini.');
    }

    public function reply(Request $request, int $ticket, VendorTicketService $service): RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:4000'],
        ]);

        $service->reply($ticket, $validated['body']);

        return back()->with('success', 'Balasan terkirim.');
    }

    public function close(Request $request, int $ticket, VendorTicketService $service): RedirectResponse
    {
        $service->close($ticket);

        return back()->with('success', 'Tiket ditandai selesai.');
    }
}
