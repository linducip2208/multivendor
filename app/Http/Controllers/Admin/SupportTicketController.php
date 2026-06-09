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
        $tickets = $query->paginate(15);
        return view('admin.support-tickets.index',compact('tickets'));
    }
    public function show(SupportTicket $ticket) { $ticket->load('replies.user'); return view('admin.support-tickets.show',compact('ticket')); }
    public function update(Request $request, SupportTicket $ticket) {
        $ticket->update(['status'=>$request->status,'assigned_to'=>auth('admin')->id()]);
        if($request->message) $ticket->replies()->create(['user_id'=>auth('admin')->id(),'message'=>$request->message]);
        return back()->with('success','Tiket diperbarui.');
    }
}
