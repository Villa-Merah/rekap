@extends('work.layout')
@section('title', 'Verifikasi')
@section('subtitle', 'Periksa bukti terbaru dan pantau hasil pemeriksaan tugas sesuai hak akses Anda.')
@section('content')
<section class="panel"><h2>Daftar pemeriksaan</h2><p class="muted hint">{{ auth()->user()->role === 'Supervisor' ? 'Supervisor dapat memeriksa tugas lintas cabang.' : 'Hanya penugasan yang menunjuk Anda sebagai pemeriksa.' }} Tugas sendiri tidak dapat diverifikasi.</p>
<form class="filter-bar user-directory-filters" method="get" action="{{ route('verifications.index') }}"><input type="search" name="q" value="{{ request('q') }}" maxlength="100" placeholder="Cari tugas atau nama penerima…" aria-label="Cari verifikasi"><select name="status" aria-label="Status verifikasi">@foreach(['Menunggu verifikasi', 'Disetujui', 'Perlu perbaikan', 'Ditolak', 'semua'] as $status)<option value="{{ $status }}" @selected($selectedStatus === $status)>{{ $status === 'semua' ? 'Semua status pemeriksaan' : $status }}</option>@endforeach</select><button class="outline">Tampilkan</button><a class="outline" href="{{ route('verifications.index') }}">Reset</a></form>
<p class="hint">{{ $items->total() }} penugasan ditemukan · setiap baris menampilkan pengiriman terbaru.</p>
<div class="table-scroll"><table class="user-data-table verification-table"><thead><tr><th>Tugas</th><th>Penerima</th><th>Cabang</th><th>Pengiriman</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
@forelse($items as $item)<tr><td data-label="Tugas"><div><strong>{{ $item->title }}</strong><small>{{ $item->platform }}</small></div></td><td data-label="Penerima">{{ $item->recipient_name }}</td><td data-label="Cabang">{{ $item->branch }}</td><td data-label="Pengiriman"><div>Revisi {{ $item->revision }}<small>{{ \Carbon\CarbonImmutable::parse($item->submitted_at, 'UTC')->setTimezone(config('workflow.timezone'))->format('d M Y H:i') }}</small></div></td><td data-label="Status"><span class="pill">{{ $item->status }}</span></td><td data-label="Aksi"><a class="text-link" href="{{ route('verifications.show', $item->assignment_id) }}">{{ $item->status === 'Menunggu verifikasi' ? 'Periksa bukti →' : 'Lihat hasil →' }}</a></td></tr>
@empty<tr><td class="user-empty" colspan="6">Tidak ada bukti yang sesuai filter pemeriksaan.</td></tr>@endforelse
</tbody></table></div>{{ $items->onEachSide(1)->links() }}</section>
@endsection
