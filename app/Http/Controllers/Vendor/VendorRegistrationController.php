<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Services\Vendor\VendorRegistrationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VendorRegistrationController extends Controller
{
    public function __construct(private readonly VendorRegistrationService $registrations) {}

    public function create(): View
    {
        return view('vendor.auth.register', [
            'tiers' => $this->registrations->tiers(),
            'documents' => VendorRegistrationService::DOCUMENT_KINDS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'shop_name' => ['required', 'string', 'min:3', 'max:160'],
            'owner_name' => ['required', 'string', 'min:3', 'max:120'],
            'email' => ['required', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['nullable', 'string', 'max:80'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:12'],
            'address' => ['nullable', 'string', 'max:500'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_account_name' => ['nullable', 'string', 'max:120'],
            'bank_account_number' => ['nullable', 'string', 'max:64', 'regex:/^[0-9\-\s]{6,64}$/'],
            'documents' => ['nullable', 'array'],
            'documents.*.kind' => ['required', 'in:identity,business_license,tax_document,bank_letter,selfie,other'],
            'documents.*.file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:4096'],
        ], [
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'documents.*.file.max' => 'Ukuran maksimal berkas adalah 4 MB.',
        ]);

        $documents = [];

        foreach ($request->file('documents', []) as $upload) {
            if (! is_array($upload) || ! isset($upload['file'])) {
                continue;
            }

            $file = $upload['file'];
            $documents[] = [
                'kind' => (string) ($upload['kind'] ?? 'other'),
                'path' => $file->store('vendor-applications', 'public'),
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ];
        }

        $application = $this->registrations->submit($validated, $documents);

        return redirect()
            ->route('vendor.register')
            ->with('reference', (string) $application->reference)
            ->with('success', 'Pengajuan toko diterima dan sedang ditinjau.');
    }

    public function status(Request $request): View
    {
        $reference = (string) $request->session()->get('reference', $request->query('reference', ''));
        $application = $reference !== '' ? $this->registrations->findByReference($reference) : null;

        if ($application !== null) {
            $application->bank_account_number = \App\Services\Vendor\VendorScope::maskAccount(
                (string) $application->bank_account_number
            );
        }

        return view('vendor.auth.register-status', [
            'application' => $application,
            'reference' => $reference,
            'tiers' => $this->registrations->tiers(),
        ]);
    }
}
