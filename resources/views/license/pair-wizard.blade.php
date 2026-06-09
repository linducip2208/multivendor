<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Aktivasi Aplikasi</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  @keyframes pulse-ring { 0%{box-shadow:0 0 0 0 rgba(99,102,241,.4)} 70%{box-shadow:0 0 0 12px rgba(99,102,241,0)} 100%{box-shadow:0 0 0 0 rgba(99,102,241,0)} }
  .pulse-ring { animation: pulse-ring 2.5s cubic-bezier(.66,0,0,1) infinite }
  body { font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial }
</style>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 flex items-center justify-center px-4 py-10">

<div class="w-full max-w-xl">
  <div class="text-center mb-6">
    <div class="inline-flex items-center justify-center w-16 h-16 bg-indigo-500/10 rounded-2xl border border-indigo-400/30 mb-4 pulse-ring">
      <svg class="w-8 h-8 text-indigo-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
    </div>
    <h1 class="text-3xl font-bold text-white">Aktivasi Aplikasi</h1>
    <p class="text-slate-400 mt-2">Aplikasi ini perlu di-aktivasi sebelum bisa digunakan.</p>
  </div>

  <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
    <form method="POST" action="/__pair" class="p-7 space-y-5">
      @csrf

      @if($error)
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 flex items-start gap-3">
          <svg class="w-5 h-5 text-red-500 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
          <div class="text-sm text-red-700 leading-relaxed">{{ $error }}</div>
        </div>
      @endif

      <div>
        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Domain Terdeteksi</label>
        <div class="flex items-center gap-2 p-3.5 bg-slate-50 border border-slate-200 rounded-lg">
          <svg class="w-5 h-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          <span class="font-mono text-slate-800">{{ $domain }}</span>
          <span class="ml-auto text-xs text-slate-400">auto</span>
        </div>
        <p class="text-xs text-slate-500 mt-1.5">Domain di-deteksi otomatis dari browser kamu — tidak bisa diubah manual.</p>
      </div>

      <div>
        <label for="activation_key" class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Activation Key</label>
        <input
          type="text"
          name="activation_key"
          id="activation_key"
          value="{{ $old_key }}"
          placeholder="XXXXX-XXXXX-XXXXX-XXXXX"
          autocomplete="off"
          autofocus
          class="block w-full px-4 py-3.5 bg-white border border-slate-300 rounded-lg font-mono uppercase tracking-wider text-center text-slate-800 placeholder:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
          oninput="this.value = this.value.toUpperCase()"
        >
        <p class="text-xs text-slate-500 mt-1.5">Format: 4 grup × 5 karakter, dipisahkan tanda hubung.</p>
      </div>

      <button
        type="submit"
        id="submitBtn"
        class="w-full inline-flex items-center justify-center gap-2 py-3.5 px-6 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg transition shadow-lg shadow-indigo-500/30 disabled:opacity-50 disabled:cursor-not-allowed"
      >
        <span id="submitText">Aktivasi</span>
        <svg id="submitIcon" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
      </button>
    </form>

    <div class="border-t border-slate-100 bg-slate-50 px-7 py-5">
      <p class="text-xs text-slate-500 text-center leading-relaxed">
        Belum punya activation key?
        <a href="{{ $marketplace_url }}/user/licenses" target="_blank" class="text-indigo-600 hover:text-indigo-700 font-medium">
          Buka marketplace ↗
        </a>
        — login → /user/licenses → copy key dari kartu lisensimu.
      </p>
    </div>
  </div>

  <p class="text-center text-xs text-slate-500 mt-6">
    Setelah aktivasi, file <code class="text-slate-300">.license.lock</code> akan dibuat otomatis. Domain ter-bind permanen sampai di-revoke dari marketplace.
  </p>
</div>

<script>
  document.querySelector('form').addEventListener('submit', function() {
    const btn  = document.getElementById('submitBtn');
    const text = document.getElementById('submitText');
    btn.disabled = true;
    text.textContent = 'Memvalidasi key...';
  });
</script>

</body>
</html>
