<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Services\Vendor\VendorCustomerService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request, VendorCustomerService $customers): View
    {
        $segments = ['vip', 'repeat', 'new', 'at_risk', 'dormant'];

        $data = $customers->index(
            VendorScopeRequest::search($request),
            VendorScopeRequest::enum($request, 'segment', $segments),
        );

        return view('vendor.customers.index', [
            'customers' => $data['customers'],
            'segments' => $data['segments'],
            'stats' => $data['stats'],
            'search' => $data['search'],
            'selected' => $data['selected'],
        ]);
    }

    public function show(Request $request, int $customer, VendorCustomerService $customers): View
    {
        $data = $customers->show($customer);

        return view('vendor.customers.show', [
            'customer' => $data['customer'],
            'orders' => $data['orders'],
            'products' => $data['products'],
            'reviews' => $data['reviews'],
            'activity' => $data['activity'],
            'segments' => $data['segments'],
            'stats' => $data['stats'],
        ]);
    }
}
