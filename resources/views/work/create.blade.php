@extends('work.layout')
@section('title', 'Buat tugas')
@section('subtitle', 'Bagikan instruksi yang jelas dan tentukan penerima serta persyaratan bukti.')
@section('content')
<form method="post" action="{{ route('tasks.store') }}" class="panel form-grid">@csrf
<label class="full">Judul tugas<input name="title" maxlength="180" value="{{ old('title') }}" required></label>
<label class="full">Deskripsi<textarea name="description" rows="4" maxlength="5000" required>{{ old('description') }}</textarea></label>
<label>Cabang<select name="branch" id="task-branch" required>@foreach($members->pluck('branch')->unique() as $branch)<option @selected(old('branch', auth()->user()->branch) === $branch)>{{ $branch }}</option>@endforeach</select></label>
<label>Platform<select name="platform" id="task-platform" required>@foreach(config('workflow.platforms') as $platform => $activities)<option @selected(old('platform') === $platform)>{{ $platform }}</option>@endforeach</select></label>
<label class="full">URL postingan target<input name="target_url" type="url" placeholder="https://…" value="{{ old('target_url') }}" maxlength="2000" required></label>
<fieldset class="full"><legend>Aktivitas yang diwajibkan</legend><div class="checkbox-list">@foreach(['like', 'komentar', 'share', 'save', 'view', 'repost'] as $activity)<label data-activity="{{ $activity }}"><input type="checkbox" name="activities[]" value="{{ $activity }}" @checked(in_array($activity, old('activities', [])))>{{ ucfirst($activity) }}</label>@endforeach</div><small>Pilihan mengikuti kemampuan platform. Seluruh aktivitas terpilih diwajibkan pada setiap akun yang digunakan.</small></fieldset>
<label class="full">Instruksi komentar (opsional)<textarea name="comment_instructions" maxlength="3000">{{ old('comment_instructions') }}</textarea></label>
<label>Jumlah akun per penerima<input name="account_count" type="number" min="1" max="20" value="{{ old('account_count', 1) }}" required></label>
<label>Prioritas<select name="priority">@foreach(['Normal', 'Tinggi', 'Mendesak'] as $priority)<option @selected(old('priority') === $priority)>{{ $priority }}</option>@endforeach</select></label>
<label>Mulai ({{ config('workflow.timezone') }})<input name="starts_at" type="datetime-local" value="{{ old('starts_at', now()->setTimezone(config('workflow.timezone'))->format('Y-m-d\TH:i')) }}" required></label>
<label>Tenggat ({{ config('workflow.timezone') }})<input name="due_at" type="datetime-local" value="{{ old('due_at') }}" required></label>
<label class="full">Persyaratan bukti<textarea name="evidence_instructions" maxlength="3000" required>{{ old('evidence_instructions', 'Unggah screenshot yang menampilkan postingan target dan aktivitas pada akun yang digunakan dengan jelas.') }}</textarea></label>
<fieldset class="full"><legend>Penerima tugas</legend><button type="button" class="outline" id="select-branch">Pilih seluruh anggota cabang</button><div class="recipient-list">@foreach($members as $member)<label data-branch="{{ $member->branch }}"><input name="recipients[]" value="{{ $member->id }}" type="checkbox" @checked(in_array($member->id, old('recipients', [])))><span>{{ $member->name }}<small>{{ $member->role }} · {{ $member->branch }}</small></span></label>@endforeach</div><small>Tugas pribadi memerlukan atasan pemeriksa yang ditetapkan oleh Superadmin.</small></fieldset>
<div class="full form-actions"><a class="outline" href="{{ route('tasks.index') }}">Kembali</a><button class="primary">Bagikan tugas & kirim notifikasi</button></div></form>
<script type="application/json" id="platform-options">@json(config('workflow.platforms'))</script>
@endsection
