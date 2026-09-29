<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Services\Vendor\VendorAiService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AiController extends Controller
{
    public function index(Request $request, VendorAiService $ai): View
    {
        $data = $ai->index();

        return view('vendor.ai.index', [
            'features' => $data['features'],
            'usage' => $data['usage'],
            'calls' => $data['calls'],
            'cost' => $data['cost'],
            'provider' => $data['provider'],
            'quota' => $data['quota'],
            'result' => $request->session()->get('ai_result'),
            'feature' => VendorScopeRequest::enum($request, 'feature', array_keys($data['features'])),
        ]);
    }

    public function generate(Request $request, VendorAiService $ai): RedirectResponse
    {
        $validated = $request->validate([
            'feature' => ['required', 'in:product_description,store_bio,customer_reply,sales_insight'],
            'product_id' => ['nullable', 'integer'],
            'order_id' => ['nullable', 'integer'],
            'message' => ['nullable', 'string', 'max:1000'],
            'tone' => ['nullable', 'string', 'max:40'],
            'length' => ['nullable', 'string', 'max:40'],
        ]);

        $output = $ai->generate($validated['feature'], $validated);

        return back()
            ->withInput($request->except('_token'))
            ->with('success', 'Saran AI siap digunakan.')
            ->with('ai_result', [
                'feature' => $validated['feature'],
                'label' => VendorAiService::FEATURES[$validated['feature']],
                'content' => $output,
            ]);
    }
}
