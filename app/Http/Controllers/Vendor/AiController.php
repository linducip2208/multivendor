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
            'feature' => ['required', 'in:product_description,store_bio,customer_reply,sales_insight,review_summary'],
            'product_id' => ['nullable', 'integer'],
            'order_id' => ['nullable', 'integer'],
            'message' => ['nullable', 'string', 'max:1000'],
            'tone' => ['nullable', 'string', 'max:40'],
            'length' => ['nullable', 'string', 'max:40'],
        ]);

        try {
            $output = $ai->generate($validated['feature'], $validated);
        } catch (\Throwable) {
            return back()
                ->withInput($request->except('_token'))
                ->with('error', 'Layanan AI sedang tidak tersedia. Silakan coba lagi nanti.');
        }

        return back()
            ->withInput($request->except('_token'))
            ->with('success', 'Saran AI siap digunakan.')
            ->with('ai_result', [
                'feature' => $validated['feature'],
                'label' => VendorAiService::FEATURES[$validated['feature']],
                'content' => $output,
            ]);
    }

    /**
     * Tiga opsi balasan chat dari konteks percakapan + order terakhir (aditif, JSON, fallback lokal bila AI off).
     */
    public function suggestReplies(Request $request, VendorAiService $ai): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'message' => ['nullable', 'string', 'max:1000'],
            'order_id' => ['nullable', 'integer'],
        ]);

        try {
            $hasil = $ai->suggestChatReplies(
                (string) ($validated['message'] ?? ''),
                isset($validated['order_id']) ? (int) $validated['order_id'] : null,
            );

            return response()->json(['success' => true] + $hasil);
        } catch (\Throwable) {
            return response()->json([
                'success' => true,
                'options' => \App\Services\Ai\BalasanChat::fallbackOptions((string) ($validated['message'] ?? ''), []),
                'topik' => 'umum',
                'source' => 'fallback',
            ]);
        }
    }

    /**
     * Generator deskripsi produk + judul SEO (aditif, JSON, fallback lokal bila AI off).
     */
    public function describeProduct(Request $request, VendorAiService $ai): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['nullable', 'integer'],
            'spesifikasi' => ['nullable', 'array'],
            'spesifikasi.*' => ['nullable', 'string', 'max:300'],
        ]);

        try {
            $hasil = $ai->describeProduct(
                isset($validated['product_id']) ? (int) $validated['product_id'] : null,
                is_array($validated['spesifikasi'] ?? null) ? $validated['spesifikasi'] : [],
            );

            return response()->json(['success' => true] + $hasil);
        } catch (\Throwable) {
            return response()->json([
                'success' => true,
                'judul_seo' => 'Produk',
                'deskripsi' => 'Deskripsi produk belum tersedia.',
                'meta_description' => 'Produk original berkualitas.',
                'source' => 'fallback',
            ]);
        }
    }

    /**
     * Ringkasan ulasan produk milik toko (aditif, JSON, fallback lokal bila AI off).
     */
    public function summarizeReviews(Request $request, VendorAiService $ai): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer'],
        ]);

        try {
            $hasil = $ai->summarizeProductReviews((int) $validated['product_id']);

            return response()->json(['success' => true] + $hasil);
        } catch (\Throwable) {
            return response()->json([
                'success' => true,
                'total' => 0,
                'rata_rata' => 0.0,
                'distribusi' => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0],
                'pro' => [],
                'kontra' => [],
                'ringkasan' => 'Belum ada ulasan yang dapat diringkas.',
                'source' => 'fallback',
            ]);
        }
    }
}
