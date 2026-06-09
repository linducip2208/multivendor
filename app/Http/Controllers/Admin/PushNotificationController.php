<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PushNotification;
use Illuminate\Http\Request;

class PushNotificationController extends Controller
{
    public function index()
    {
        $notifications = PushNotification::latest()->paginate(15);
        return view('admin.push-notifications.index', compact('notifications'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate(['title' => 'required|string|max:255', 'description' => 'required|string', 'image' => 'nullable|string|max:500', 'target_url' => 'nullable|string|max:500', 'target_type' => 'required|in:all,customer,vendor']);
        PushNotification::create($validated);
        return redirect()->route('admin.push-notifications.index')->with('success', 'Notifikasi dibuat.');
    }

    public function send(PushNotification $notification)
    {
        $notification->update(['sent' => true, 'sent_at' => now()]);
        return back()->with('success', 'Notifikasi dikirim ke semua ' . $notification->target_type . '.');
    }
}
