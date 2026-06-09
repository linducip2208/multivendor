<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Provider;
use Illuminate\Http\Request;

class ProviderController extends Controller
{
    public function index(Request $request)
    {
        $query = Provider::latest();
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        $providers = $query->paginate(15)->withQueryString();
        return view('admin.providers.index', compact('providers'));
    }

    public function create()
    {
        $presets = $this->loadPresets();
        return view('admin.providers.create', compact('presets'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:payment,shipping,sms,mail,ai,storage',
            'api_format' => 'required|string|max:50',
            'base_url' => 'nullable|url',
            'api_key' => 'nullable|string',
            'api_secret' => 'nullable|string',
            'extra_headers' => 'nullable|json',
            'config' => 'nullable|json',
            'is_active' => 'boolean',
            'description' => 'nullable|string',
        ]);

        $provider = new Provider();
        $provider->name = $validated['name'];
        $provider->type = $validated['type'];
        $provider->api_format = $validated['api_format'];
        $provider->base_url = $validated['base_url'];
        $provider->extra_headers = $validated['extra_headers'] ? json_decode($validated['extra_headers'], true) : null;
        $provider->config = $validated['config'] ? json_decode($validated['config'], true) : null;
        $provider->is_active = $request->boolean('is_active');
        $provider->description = $validated['description'];

        if ($validated['api_key']) {
            $provider->setApiKeyEncryptedAttribute($validated['api_key']);
        }
        if ($validated['api_secret']) {
            $provider->setApiSecretEncryptedAttribute($validated['api_secret']);
        }

        $provider->save();

        return redirect()->route('admin.providers.index')->with('success', 'Provider berhasil ditambahkan.');
    }

    public function edit(Provider $provider)
    {
        $presets = $this->loadPresets();
        return view('admin.providers.edit', compact('provider', 'presets'));
    }

    public function update(Request $request, Provider $provider)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'api_format' => 'required|string|max:50',
            'base_url' => 'nullable|url',
            'api_key' => 'nullable|string',
            'api_secret' => 'nullable|string',
            'extra_headers' => 'nullable|json',
            'config' => 'nullable|json',
            'is_active' => 'boolean',
            'description' => 'nullable|string',
        ]);

        $provider->name = $validated['name'];
        $provider->api_format = $validated['api_format'];
        $provider->base_url = $validated['base_url'];
        $provider->extra_headers = $validated['extra_headers'] ? json_decode($validated['extra_headers'], true) : null;
        $provider->config = $validated['config'] ? json_decode($validated['config'], true) : null;
        $provider->is_active = $request->boolean('is_active');
        $provider->description = $validated['description'];

        if ($validated['api_key']) {
            $provider->setApiKeyEncryptedAttribute($validated['api_key']);
        }
        if ($validated['api_secret']) {
            $provider->setApiSecretEncryptedAttribute($validated['api_secret']);
        }

        $provider->save();

        return redirect()->route('admin.providers.index')->with('success', 'Provider diperbarui.');
    }

    public function destroy(Provider $provider)
    {
        $provider->delete();
        return back()->with('success', 'Provider dihapus.');
    }

    public function getPreset(Request $request)
    {
        $type = $request->type;
        $format = $request->format;
        $presets = $this->loadPresets();

        $key = $type === 'payment' ? 'payment-presets' : 'shipping-presets';
        $list = $presets[$key] ?? [];

        foreach ($list as $preset) {
            if ($preset['api_format'] === $format) {
                return response()->json($preset);
            }
        }
        return response()->json(null);
    }

    protected function loadPresets(): array
    {
        $payment = [];
        $shipping = [];
        $ai = [];

        $paymentFile = storage_path('app/presets/payment-presets.json');
        $shippingFile = storage_path('app/presets/shipping-presets.json');
        $aiFile = storage_path('app/presets/ai-presets.json');

        if (file_exists($paymentFile)) {
            $data = json_decode(file_get_contents($paymentFile), true);
            $payment = $data['payment-presets'] ?? [];
        }
        if (file_exists($shippingFile)) {
            $data = json_decode(file_get_contents($shippingFile), true);
            $shipping = $data['shipping-presets'] ?? [];
        }
        if (file_exists($aiFile)) {
            $data = json_decode(file_get_contents($aiFile), true);
            $ai = $data['ai-presets'] ?? [];
        }

        return ['payment-presets' => $payment, 'shipping-presets' => $shipping, 'ai-presets' => $ai];
    }
}
