<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Models\VatTax;
use Illuminate\Http\Request;

class TaxReportController extends Controller
{
    public function index(Request $request)
    {
        $year = $request->get('year', now()->year);
        $month = $request->get('month', now()->month);

        $vatTaxes = VatTax::where('is_active', true)->get();

        $taxCollected = \App\Models\Order::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->whereIn('order_status', ['delivered', 'confirmed', 'processing', 'shipped'])
            ->sum('tax');

        $taxableSales = \App\Models\Order::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->whereIn('order_status', ['delivered', 'confirmed', 'processing', 'shipped'])
            ->sum('sub_total');

        $monthlyTaxes = \App\Models\Order::whereYear('created_at', $year)
            ->whereIn('order_status', ['delivered', 'confirmed', 'processing', 'shipped'])
            ->selectRaw('MONTH(created_at) as month, SUM(tax) as total_tax, SUM(sub_total) as total_sales')
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        return view('admin.tax-report.index', compact('vatTaxes', 'taxCollected', 'taxableSales', 'monthlyTaxes', 'year', 'month'));
    }

    public function settings()
    {
        return view('admin.tax-report.settings');
    }

    public function updateSettings(Request $request)
    {
        SystemSetting::set('tax_number', $request->tax_number);
        SystemSetting::set('tax_company_name', $request->tax_company_name);
        SystemSetting::set('tax_address', $request->tax_address);
        SystemSetting::set('tax_default_rate', $request->tax_default_rate);
        SystemSetting::set('tax_include_in_price', $request->boolean('tax_include_in_price') ? '1' : null);

        return back()->with('success', 'Pengaturan pajak disimpan.');
    }
}
