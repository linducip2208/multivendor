@extends('layouts.admin')

@section('title', 'AI Copilot')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['AI', ['label' => 'Copilot']]" />
@endsection

@section('content')
    <x-admin.page-header title="AI Copilot" subtitle="Analisis konsultatif berdasarkan agregat data katalog dan transaksi.">
        <x-slot:actions>
            <a href="{{ route('admin.ai.prompts') }}" class="btn btn-outline-secondary btn-sm">Prompt</a>
            <a href="{{ route('admin.ai.usage') }}" class="btn btn-outline-secondary btn-sm">Pemakaian</a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.alert type="warning" :title="$disclaimer" icon="alert-triangle" />

    <div class="row g-3">
        <div class="col-lg-4">
            <x-admin.card title="Konsol" icon="bot" class="mb-3">
                @forelse ($providers as $provider)
                    <div class="d-flex justify-content-between align-items-center border rounded-3 p-2 mb-2">
                        <div>
                            <p class="mb-0 fw-semibold small">{{ $provider['name'] }}</p>
                            <small class="text-secondary">{{ $provider['model'] !== '' ? $provider['model'] : 'model bawaan' }}</small>
                        </div>
                        <x-admin.badge :text="$provider['configured'] ? 'Siap' : 'Tanpa kunci'" :color="$provider['configured'] ? 'success' : 'warning'" pill />
                    </div>
                @empty
                    <x-admin.empty-state
                        compact
                        icon="plug"
                        title="Belum ada AI provider"
                        text="Tambahkan provider tipe AI di menu Integrasi."
                    />
                @endforelse
            </x-admin.card>

            <x-admin.card title="Konteks Periode" icon="calendar" :subtitle="$range['from'].' sampai '.$range['to'].' ('.$range['days'].' hari)'" class="mb-3">
                <form method="GET" action="{{ route('admin.ai.index') }}">
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small mb-1" for="ai-from">Dari</label>
                            <input type="date" class="form-control form-control-sm" id="ai-from" name="from" value="{{ $range['from'] }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label small mb-1" for="ai-to">Sampai</label>
                            <input type="date" class="form-control form-control-sm" id="ai-to" name="to" value="{{ $range['to'] }}">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-outline-secondary btn-sm w-100 mt-2">Terapkan</button>
                </form>
            </x-admin.card>

            <x-admin.card title="Pemakaian Terakhir" icon="activity" flush>
                <ul class="list-group list-group-flush">
                    @forelse ($recent as $run)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <p class="mb-0 small fw-semibold">{{ $run['label'] }}</p>
                                <small class="text-secondary">{{ $run['user'] }} · {{ $run['at'] }}</small>
                            </div>
                            <div class="text-end">
                                <x-admin.badge :text="$run['success'] ? 'OK' : 'Gagal'" :color="$run['success'] ? 'success' : 'danger'" pill />
                                <small class="d-block text-secondary">{{ number_format($run['tokens'], 0, ',', '.') }} token</small>
                            </div>
                        </li>
                    @empty
                        <li class="list-group-item text-secondary small">Belum ada pemakaian AI.</li>
                    @endforelse
                </ul>
            </x-admin.card>
        </div>

        <div class="col-lg-8">
            <x-admin.card title="Kesiapan Copilot" icon="sparkles">
                <div class="row g-3">
                    @foreach (['gmv', 'net_revenue', 'orders', 'aov', 'refunds', 'commission', 'payouts'] as $key)
                        @php $tile = $summary[$key] ?? null; @endphp
                        @if ($tile)
                            <div class="col-6 col-md-4">
                                <x-admin.stat :label="$tile['label']" :value="$tile['value']" :money="$tile['money']" />
                            </div>
                        @endif
                    @endforeach
                </div>
            </x-admin.card>

            <x-admin.card title="Jalankan Tugas" icon="play" class="mt-3" subtitle="Pilih tugas, provider, lalu baca hasilnya.">
                <form id="copilot-form">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="copilot-task">Tugas</label>
                            <select class="form-select" id="copilot-task" name="task" required>
                                @foreach ($tasks as $key => $task)
                                    <option value="{{ $key }}">{{ $task['label'] }}</option>
                                @endforeach
                            </select>
                            <small class="text-secondary" id="copilot-task-help">{{ $tasks[array_key_first($tasks)]['description'] }}</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="copilot-provider">Provider</label>
                            <select class="form-select" id="copilot-provider" name="provider_id" required>
                                @foreach ($providers as $provider)
                                    <option value="{{ $provider['id'] }}">{{ $provider['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="copilot-model">Model</label>
                            <input type="text" class="form-control" id="copilot-model" name="model" placeholder="Kosongkan untuk model bawaan">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="copilot-note">Catatan Operator</label>
                            <input type="text" class="form-control" id="copilot-note" name="note" maxlength="1000" placeholder="Pertanyaan khusus yang perlu dijawab">
                        </div>
                    </div>

                    <input type="hidden" name="from" value="{{ $range['from'] }}">
                    <input type="hidden" name="to" value="{{ $range['to'] }}">

                    <button type="submit" class="btn btn-primary mt-3" @disabled($providers === [])>
                        <x-admin.icon name="sparkles" :size="14" /> Jalankan
                    </button>
                </form>

                <div class="mt-3" id="copilot-status" aria-live="polite"></div>
            </x-admin.card>

            <x-admin.card title="Hasil" icon="message-square" class="mt-3">
                <div id="copilot-result">
                    <x-admin.empty-state
                        icon="sparkles"
                        title="Belum ada hasil"
                        text="Jalankan salah satu tugas di atas. Output bersifat consultatif dan tidak mengubah data apa pun."
                    />
                </div>
            </x-admin.card>

            @includeIf('admin.ai._expansion')
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    var form = document.getElementById('copilot-form');
    var statusBox = document.getElementById('copilot-status');
    var resultBox = document.getElementById('copilot-result');
    var taskSelect = document.getElementById('copilot-task');
    var taskHelp = document.getElementById('copilot-task-help');
    var descriptions = @json(collect($tasks)->mapWithKeys(fn (array $task, string $key): array => [$key => $task['description']])->all());

    if (!form) {
        return;
    }

    taskSelect?.addEventListener('change', function () {
        taskHelp.textContent = descriptions[this.value] || '';
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        var button = form.querySelector('button[type="submit"]');
        button.disabled = true;
        statusBox.innerHTML = '<div class="d-flex align-items-center gap-2"><span class="spinner-border spinner-border-sm" role="status"></span><span class="small text-secondary">AI sedang menganalisis data…</span></div>';

        var payload = {};
        new FormData(form).forEach(function (value, key) {
            payload[key] = value;
        });
        payload.range = 'custom';

        fetch('{{ route('admin.ai.generate') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify(payload)
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data.success) {
                    resultBox.innerHTML = '<div class="alert alert-warning mb-0">' + (data.error || 'Provider tidak merespons.') + '</div>';
                    statusBox.innerHTML = '';
                    return;
                }

                var meta = document.createElement('p');
                meta.className = 'small text-secondary mb-2';
                meta.textContent = 'Model: ' + (data.model || '-') + ' · ' + (data.tokens?.total || 0) + ' token · ' + (data.duration_ms || 0) + ' ms';

                var notice = document.createElement('p');
                notice.className = 'small text-warning mb-2';
                notice.textContent = 'Teks consultatif. Tidak ada aksi keuangan yang dijalankan.';

                var body = document.createElement('div');
                body.className = 'border rounded-3 p-3 small';
                body.style.whiteSpace = 'pre-wrap';
                body.textContent = data.content || '';

                resultBox.replaceChildren(notice, meta, body);
                statusBox.innerHTML = '';
            })
            .catch(function (error) {
                resultBox.innerHTML = '<div class="alert alert-danger mb-0">Permintaan gagal: ' + error.message + '</div>';
                statusBox.innerHTML = '';
            })
            .finally(function () {
                button.disabled = false;
            });
    });
})();
</script>
@endpush
