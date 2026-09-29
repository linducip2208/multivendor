@extends('layouts.admin')

@section('title', 'Prompt Templates')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['AI', ['label' => 'Prompt']]" />
@endsection

@section('content')
    <x-admin.page-header title="Prompt Template" subtitle="System prompt per tugas. Guardrail selalu ditambahkan otomatis." />

    <x-admin.alert type="warning" :title="'Guardrail tidak dapat dihapus'" icon="shield">
        {{ $guardrail }}
    </x-admin.alert>

    <x-admin.card title="Daftar Tugas" icon="file-code" flush>
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover">
                <thead>
                    <tr>
                        <th scope="col">Tugas</th>
                        <th scope="col">Deskripsi</th>
                        <th scope="col" class="text-center">Status Prompt</th>
                        <th scope="col" class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tasks as $key => $task)
                        <tr>
                            <td class="fw-semibold">{{ $task['label'] }}</td>
                            <td class="text-secondary">{{ $task['description'] }}</td>
                            <td class="text-center">
                                <x-admin.badge :text="$task['customised'] ? 'Disesuaikan' : 'Bawaan'" :color="$task['customised'] ? 'info' : 'secondary'" pill />
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm" role="group" aria-label="Aksi prompt {{ $task['label'] }}">
                                    <button
                                        type="button"
                                        class="btn btn-outline-primary"
                                        data-bs-toggle="modal"
                                        data-bs-target="#prompt-{{ $key }}"
                                        aria-label="Ubah prompt {{ $task['label'] }}"
                                        aria-haspopup="dialog"
                                    >
                                        <x-admin.icon name="pencil" :size="14" /> Ubah
                                    </button>
                                    @if ($task['customised'])
                                        <form method="POST" action="{{ route('admin.ai.prompts.reset', ['task' => $key]) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-secondary">Kembalikan Bawaan</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>

                        <x-admin.modal :id="'prompt-'.$key" :title="'Prompt '.$task['label']" icon="pencil" size="lg">
                            <form method="POST" action="{{ route('admin.ai.prompts.update', ['task' => $key]) }}">
                                @csrf
                                <x-admin.form-field
                                    name="system"
                                    label="System Prompt"
                                    type="textarea"
                                    :rows="14"
                                    :value="$task['system']"
                                    required
                                    :minlength="20"
                                    :maxlength="4000"
                                />
                                <details class="mb-3">
                                    <summary class="small text-secondary">Lihat bawaan sistem</summary>
                                    <pre class="small border rounded-3 p-2 mt-2" style="white-space: pre-wrap;">{{ $task['default'] }}</pre>
                                </details>
                                <div class="d-flex justify-content-end gap-2">
                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn btn-primary">Simpan Prompt</button>
                                </div>
                            </form>
                        </x-admin.modal>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-admin.card>
@endsection
