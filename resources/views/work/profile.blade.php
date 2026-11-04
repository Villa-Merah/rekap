@extends('work.layout')
@section('title', 'Profil saya')
@section('subtitle', 'Identitas, seluruh riwayat tugas, progres, dan akun media sosial Anda.')
@section('heading-action')
<a class="outline" href="{{ route('password.change') }}">Ubah kata sandi</a>
@endsection
@section('content')
<section class="panel"><h2>{{ $member->name }}</h2><p>{{ $member->email }}</p><p class="muted">{{ $member->role }} · {{ $member->branch ?? 'Tanpa cabang' }}</p></section>
<div class="summary-grid">
@foreach(['received' => 'Tugas diterima', 'completed' => 'Selesai / disetujui', 'waiting' => 'Menunggu verifikasi', 'in_progress' => 'Dalam proses', 'not_started' => 'Belum dikerjakan', 'revision' => 'Perlu perbaikan', 'rejected' => 'Ditolak'] as $key => $label)
<div class="summary"><div><strong>{{ $summary[$key] }}</strong><span>{{ $label }}</span></div></div>
@endforeach
</div>
<section class="panel"><h2>Progres tugas: {{ $progress }}%</h2><progress class="profile-progress" value="{{ $progress }}" max="100" aria-label="Progres tugas disetujui {{ $progress }} persen"></progress><p class="muted hint">{{ $summary['completed'] }} dari {{ $summary['received'] }} tugas telah disetujui. Hasil yang menunggu verifikasi belum dihitung selesai. Ringkasan mencakup seluruh waktu.</p>
@if($member->role !== 'Superadmin')<a class="outline" href="{{ route('reports.index', ['user_id' => $member->id]) }}">Laporan aktivitas & PDF</a>@endif</section>
<section class="panel" id="akun-saya"><h2>Akun saya</h2><p class="muted hint">{{ $accountCounts->sum() }} akun dicatat di aplikasi. Target mengikuti pengaturan cabang.</p>
<div class="account-target-grid">
@foreach(config('workflow.account_platforms') as $platform)
@php($registered = (int) $accountCounts->get($platform, 0))
@php($target = (int) $quotas->get($platform, $member->role === 'Staff' ? config('workflow.account_target') : 0))
<article class="account-target-card"><h3>{{ $platform }}</h3><p><strong>{{ $registered }}</strong> akun terdaftar</p>
@if($target > 0)<progress value="{{ min($registered, $target) }}" max="{{ $target }}" aria-label="{{ $platform }}: {{ $registered }} dari {{ $target }} akun"></progress><p class="hint">Target {{ $target }} · {{ $registered >= $target ? 'Target terpenuhi' : 'Kurang '.($target - $registered).' akun' }}</p>@else<p class="muted hint">Tanpa target</p>@endif</article>
@endforeach
</div>
<p><a class="outline" href="{{ route('accounts.index') }}">Daftarkan akun</a></p>
<div class="table-scroll"><table><thead><tr><th>Platform</th><th>Nama akun</th><th>Username / email</th><th>Status</th><th>Terdaftar</th></tr></thead><tbody>
@forelse($accounts as $account)<tr><td>{{ $account->platform }}</td><td>{{ $account->name }}@if($account->profile_url && in_array(strtolower(parse_url($account->profile_url, PHP_URL_SCHEME) ?? ''), ['https', 'http'], true))<small><a href="{{ $account->profile_url }}" target="_blank" rel="noopener noreferrer">Buka profil ↗</a></small>@endif</td><td>{{ $account->identity }}</td><td>{{ $account->status }}</td><td>{{ $account->created_at ? \Carbon\CarbonImmutable::parse($account->created_at, 'UTC')->setTimezone(config('workflow.timezone'))->format('d M Y H:i') : '—' }}</td></tr>
@empty<tr><td colspan="5">Belum ada akun yang didaftarkan.</td></tr>@endforelse
</tbody></table></div>{{ $accounts->fragment('akun-saya')->links() }}
</section>
<section class="panel" id="tugas-saya"><h2>Riwayat tugas dan hasil pengerjaan</h2>
@forelse($assignments as $assignment)
@php($submission = $submissions->get($assignment->id))
<article class="submission"><h3>{{ $assignment->title }}</h3><p><span class="pill">{{ $assignment->platform }}</span> <span class="pill">{{ $assignment->status }}</span></p><p class="hint">Diterima {{ \Carbon\CarbonImmutable::parse($assignment->created_at, 'UTC')->setTimezone(config('workflow.timezone'))->format('d M Y H:i') }} · Tenggat {{ \Carbon\CarbonImmutable::parse($assignment->due_at, 'UTC')->setTimezone(config('workflow.timezone'))->format('d M Y H:i') }}</p><a class="text-link" href="{{ route('tasks.show', $assignment->task_id) }}">Lihat detail tugas</a>
@if($submission)<p class="hint">Pengiriman terakhir: revisi {{ $submission->revision }} · {{ $submission->status }} · {{ \Carbon\CarbonImmutable::parse($submission->created_at, 'UTC')->setTimezone(config('workflow.timezone'))->format('d M Y H:i') }}</p><p>Aktivitas dilaporkan: {{ $submission->activity_labels ?: '—' }}</p>@if($submission->notes)<p class="preserve">{{ $submission->notes }}</p>@endif @if($submission->review_notes)<p class="review-note">Catatan pemeriksa: {{ $submission->review_notes }}</p>@endif
<div class="preview-grid">@forelse($evidence->get($submission->id, collect()) as $file)<a href="{{ route('evidence.show', $file->id) }}" target="_blank" rel="noopener"><img loading="lazy" src="{{ route('evidence.show', $file->id) }}" alt="Bukti {{ $assignment->title }}"><small>{{ $file->original_name }}</small></a>@empty<p class="muted">Tidak ada gambar bukti.</p>@endforelse</div>
@else<p class="muted">Belum mengirim hasil dan bukti.</p>@endif</article>
@empty<p class="empty">Belum ada tugas yang diterima.</p>@endforelse
{{ $assignments->fragment('tugas-saya')->links() }}
</section>
@endsection
