@extends('work.layout')
@section('title', 'Laporan staf')
@section('subtitle', 'Rekap tugas, akun terdaftar, dan bukti aktivitas per staf dalam satu laporan PDF.')
@section('heading-action')
<a class="primary" href="{{ route('reports.download', ['user_id' => $member->id, 'from' => $from, 'to' => $to]) }}">Unduh PDF</a>
@endsection
@section('content')
@isset($pdfError)<p class="flash error" role="alert">{{ $pdfError }}</p>@endisset
<form class="panel form-grid" method="get" action="{{ route('reports.index') }}">
    <label>Staf / pengguna<select name="user_id">@foreach($members as $person)<option value="{{ $person->id }}" @selected($member->id === $person->id)>{{ $person->name }} · {{ $person->branch ?? 'Tanpa cabang' }}</option>@endforeach</select></label>
    <div class="form-grid"><label>Dari tanggal<input type="date" name="from" required value="{{ $from }}"></label><label>Sampai tanggal<input type="date" name="to" required value="{{ $to }}"></label></div>
    <div class="full"><button class="outline">Tampilkan laporan</button></div>
</form>
<p class="hint muted">Periode berdasarkan tanggal tugas diterima dan akun didaftarkan, zona waktu {{ config('workflow.timezone') }}. Status tugas dan bukti revisi terbaru ditampilkan saat laporan dibuat. Selesai berarti Disetujui; menunggu verifikasi belum dihitung selesai. Jumlah akun adalah akun yang dicatat di aplikasi, bukan verifikasi tanggal pembuatan di platform.</p>
<div class="summary-grid">
@foreach(['received' => 'Tugas diterima', 'completed' => 'Selesai / disetujui', 'unfinished' => 'Belum selesai', 'waiting' => 'Menunggu verifikasi', 'rejected' => 'Ditolak', 'accounts' => 'Akun didaftarkan'] as $key => $label)
<div class="summary"><div><strong>{{ $summary[$key] }}</strong><span>{{ $label }}</span></div></div>
@endforeach
</div>
<section class="panel"><h2>{{ $member->name }} · {{ $member->branch ?? 'Tanpa cabang' }}</h2><p class="muted">{{ $member->role }} · {{ $from }} sampai {{ $to }}</p><h3>Akun didaftarkan per platform</h3><div class="activity-records">@foreach(config('workflow.account_platforms') as $platform)<span class="activity-record">{{ $platform }}: {{ $accountCounts->get($platform, 0) }}</span>@endforeach</div>
<div class="table-scroll"><table><thead><tr><th>Platform</th><th>Nama akun</th><th>Identitas</th></tr></thead><tbody>@forelse($accounts as $account)<tr><td>{{ $account->platform }}</td><td>{{ $account->name }}</td><td>{{ $account->identity }}</td></tr>@empty<tr><td colspan="3">Tidak ada akun didaftarkan pada periode ini.</td></tr>@endforelse</tbody></table></div></section>
<section class="panel"><h2>Tugas dan bukti aktivitas</h2>
@forelse($assignments as $assignment)
@php($submission = $submissions->get($assignment->id))
<article class="submission"><h3>{{ $loop->iteration }}. {{ $assignment->title }}</h3><p><span class="pill">{{ $assignment->platform }}</span> <span class="pill">{{ $assignment->status }}</span></p><p class="hint">Diterima {{ \Carbon\CarbonImmutable::parse($assignment->created_at, 'UTC')->setTimezone(config('workflow.timezone'))->format('d M Y H:i') }} · Tenggat {{ \Carbon\CarbonImmutable::parse($assignment->due_at, 'UTC')->setTimezone(config('workflow.timezone'))->format('d M Y H:i') }}</p><a class="text-link" href="{{ route('tasks.show', $assignment->task_id) }}">Lihat detail tugas</a>
@if($submission)
<p class="hint">Pengiriman revisi {{ $submission->revision }} · {{ $submission->status }}</p>
@if($submission->notes)<p class="preserve">{{ $submission->notes }}</p>@endif
@if($submission->review_notes)<p class="review-note">Catatan pemeriksa: {{ $submission->review_notes }}</p>@endif
<div class="preview-grid">@forelse($evidence->get($submission->id, collect()) as $file)<a href="{{ route('evidence.show', $file->id) }}" target="_blank" rel="noopener"><img loading="lazy" src="{{ route('evidence.show', $file->id) }}" alt="Bukti {{ $assignment->title }}"><small>{{ $file->original_name }}</small></a>@empty<p class="muted">Tidak ada gambar bukti.</p>@endforelse</div>
@else<p class="muted">Belum mengirim hasil dan bukti.</p>@endif
</article>
@empty<p class="empty">Tidak ada tugas diterima pada periode ini.</p>@endforelse
</section>
@endsection
