<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Services\Vendor\VendorStaffService;
use App\Services\Vendor\VendorSubscriptionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    public function index(Request $request, VendorStaffService $staff, VendorSubscriptionService $subscriptions): View
    {
        $data = $staff->index(VendorScopeRequest::search($request));

        return view('vendor.staff.index', [
            'staff' => $data['staff'],
            'roles' => $data['roles'],
            'permissions' => $data['permissions'],
            'search' => $data['search'],
            'active' => $data['active'],
            'pending' => $data['pending'],
            'limit' => $subscriptions->limit('staff'),
            'used' => $subscriptions->usage(auth('vendor')->user()->shop)['staff'],
        ]);
    }

    public function store(Request $request, VendorStaffService $staff): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:32'],
            'role' => ['required', 'in:manager,staff,finance,warehouse,support'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'in:'.implode(',', VendorStaffService::PERMISSIONS)],
        ]);

        $staff->store($validated);

        return back()->with('success', 'Anggota tim '.$validated['name'].' berhasil diundang.');
    }

    public function destroy(Request $request, int $staff, VendorStaffService $service): RedirectResponse
    {
        $service->destroy($staff);

        return back()->with('success', 'Akses anggota tim dicabut.');
    }
}
