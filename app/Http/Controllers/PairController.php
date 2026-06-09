<?php

namespace App\Http\Controllers;

use App\Services\LicenseClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class PairController extends Controller
{
    public function __construct(private LicenseClient $client) {}

    /** GET /__pair — show wizard form (or redirect if already paired) */
    public function show(Request $request): View|\Illuminate\Http\RedirectResponse
    {
        $domain = strtolower($request->getHost());

        if ($this->client->verify($domain)) {
            return redirect('/');
        }

        return view('license.pair-wizard', [
            'domain'           => $domain,
            'marketplace_url'  => rtrim(config('license.server_url'), '/'),
            'old_key'          => $request->old('activation_key'),
            'error'            => session('pair_error'),
        ]);
    }

    /** POST /__pair — submit activation key */
    public function activate(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'activation_key' => 'required|string|min:10|max:64',
        ]);

        // Domain is server-detected; user cannot spoof
        $domain = strtolower($request->getHost());
        $key    = strtoupper(trim($request->input('activation_key')));

        $result = $this->client->activate($key, $domain);

        if (!$result['ok']) {
            return back()
                ->withInput()
                ->with('pair_error', $result['error'] ?? 'Aktivasi gagal.');
        }

        session()->flash('pair_success', $result['data']);

        return redirect('/__pair/success');
    }

    /** GET /__pair/success — post-activation confirmation page */
    public function success(Request $request): View|\Illuminate\Http\RedirectResponse
    {
        $data = session('pair_success');
        if (!$data) {
            // Direct visit — fall back to verifying current lock
            $data = $this->client->verify(strtolower($request->getHost()));
            if (!$data) return redirect('/__pair');
        }

        return view('license.pair-success', ['data' => $data]);
    }
}
