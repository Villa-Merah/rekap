@extends('work.layout')
@section('title', 'Log aktivitas')
@section('subtitle', 'Riwayat tindakan pengguna yang tercatat di aplikasi.')
@section('content')
<section class="panel">
<p class="muted hint">{{ auth()->user()->role === 'Superadmin' ? 'Menampilkan aktivitas seluruh pengguna, termasuk catatan pengguna yang sudah dihapus.' : (\App\Services\WorkAccess::manages(auth()->user()) ? 'Menampilkan aktivitas pengguna yang saat ini berada di cabang Anda, selain Superadmin.' : 'Menampilkan aktivitas akun Anda sendiri.') }} Waktu ditampilkan dalam zona {{ config('workflow.timezone') }}. Log hanya dapat dilihat.</p>
<form class="filter-bar user-directory-filters" method="get" action="{{ route('activity-logs.index') }}">
<input type="search" name="q" value="{{ request('q') }}" maxlength="100" placeholder="Cari pelaku atau aktivitas…" aria-label="Cari pelaku atau aktivitas">
<select name="action" aria-label="Jenis aktivitas"><option value="">Semua aktivitas</option>@foreach($actions as $action)<option value="{{ $action }}" @selected(request('action') === $action)>{{ $action }}</option>@endforeach</select>
<label>Dari tanggal<input type="date" name="from" value="{{ request('from') }}"></label><label>Sampai tanggal<input type="date" name="to" value="{{ request('to') }}"></label>
<button class="outline">Tampilkan</button><a class="outline" href="{{ route('activity-logs.index') }}">Reset</a>
</form>
<p class="hint">{{ $logs->total() }} aktivitas ditemukan</p>
<div class="table-scroll"><table class="user-data-table"><thead><tr><th>Waktu</th><th>Pelaku</th><th>Aktivitas</th><th>Objek</th><th>Ringkasan</th></tr></thead><tbody>
@forelse($logs as $log)
<tr><td data-label="Waktu">{{ $log->created_at ? \Carbon\CarbonImmutable::parse($log->created_at, 'UTC')->setTimezone(config('workflow.timezone'))->format('d M Y H:i:s') : '—' }}</td><td data-label="Pelaku">{{ $log->actor_name ?? 'Pengguna sudah dihapus' }}<small>{{ $log->actor_branch ?? 'Tanpa cabang' }}</small></td><td data-label="Aktivitas">{{ $log->action }}</td><td data-label="Objek">{{ ['user' => 'Pengguna', 'task' => 'Tugas', 'assignment' => 'Penugasan', 'submission' => 'Pengiriman bukti', 'account' => 'Akun media sosial'][$log->subject_type] ?? 'Objek' }} #{{ $log->subject_id }}</td><td data-label="Ringkasan">@forelse($log->summary as $label => $value)<small>{{ $label }}: {{ $value }}</small>@empty<span class="muted">—</span>@endforelse</td></tr>
@empty<tr><td colspan="5" class="user-empty">Belum ada aktivitas yang sesuai filter.</td></tr>@endforelse
</tbody></table></div>{{ $logs->links() }}
</section>
@endsection
