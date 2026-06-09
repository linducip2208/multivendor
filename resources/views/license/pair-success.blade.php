<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="refresh" content="4; url=/">
<title>Aktivasi Berhasil</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  body { font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial }
  @keyframes check-pop { 0% { transform: scale(0); opacity: 0 } 50% { transform: scale(1.2) } 100% { transform: scale(1); opacity: 1 } }
  .check-pop { animation: check-pop 0.6s cubic-bezier(.34,1.56,.64,1) }
  @keyframes fade-up { from { opacity: 0; transform: translateY(10px) } to { opacity: 1; transform: translateY(0) } }
  .fade-up { animation: fade-up 0.5s ease-out 0.3s both }
</style>
</head>
<body class="min-h-screen bg-gradient-to-br from-emerald-50 via-white to-teal-50 flex items-center justify-center px-4 py-10">

<div class="w-full max-w-xl">
  <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">

    <div class="bg-gradient-to-br from-emerald-500 to-teal-600 px-7 py-10 text-center text-white">
      <div class="inline-flex items-center justify-center w-20 h-20 bg-white/20 backdrop-blur rounded-full mb-4 check-pop">
        <svg class="w-10 h-10 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
          <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
        </svg>
      </div>
      <h1 class="text-2xl font-bold">Aktivasi Berhasil</h1>
      <p class="text-emerald-100 mt-1 text-sm">Aplikasi siap digunakan.</p>
    </div>

    <div class="p-7 space-y-5 fade-up">

      <div class="bg-slate-50 border border-slate-200 rounded-xl p-5">
        <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Produk</div>
        <div class="flex items-baseline gap-3">
          <span class="text-2xl">📦</span>
          <div>
            <div class="text-lg font-bold text-slate-900">{{ $data['product']['name'] ?? 'Aplikasi' }}</div>
            <div class="text-sm text-slate-500">Versi v{{ $data['product']['version'] ?? '1.0.0' }}</div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
          <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2 flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Domain Terkunci
          </div>
          <div class="font-mono text-sm text-slate-800 break-all">{{ $data['domain'] ?? '-' }}</div>
        </div>

        @if(!empty($data['license']['support_until']))
        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
          <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2 flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            Support Aktif Sampai
          </div>
          <div class="font-medium text-sm text-slate-800">{{ \Illuminate\Support\Carbon::parse($data['license']['support_until'])->format('d M Y') }}</div>
        </div>
        @endif
      </div>

      <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 flex items-start gap-3">
        <svg class="w-5 h-5 text-emerald-600 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
        <div class="text-sm text-emerald-800 leading-relaxed">
          <strong>Konfirmasi:</strong> nama produk di atas harus cocok dengan yang kamu beli. Kalau salah, klik "Revoke" di marketplace dan re-pair dengan key yang benar.
        </div>
      </div>

      <a href="/" class="block w-full text-center py-3.5 px-6 bg-slate-900 hover:bg-slate-800 text-white font-semibold rounded-lg transition">
        Masuk ke Aplikasi →
      </a>

      <p class="text-center text-xs text-slate-500">
        Auto-redirect dalam 4 detik...
      </p>
    </div>
  </div>
</div>

</body>
</html>
