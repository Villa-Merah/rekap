@extends('work.layout')
@section('title', 'Data pengguna')
@section('subtitle', 'Lihat data anggota organisasi, jabatan, dan cabangnya.')
@section('heading-action')<a class="primary" href="{{ route('users.create') }}">+ Tambah pengguna</a>@endsection
@section('content')
<section class="panel">
    <div class="panel-title"><h2>Daftar pengguna</h2><span>{{ $users->total() }} hasil · {{ $totalMembers }} pengguna dalam akses Anda</span></div>
    <form class="filter-bar user-directory-filters" method="get" action="{{ route('users.index') }}">
        <input name="q" type="search" value="{{ request('q') }}" maxlength="100" placeholder="Cari nama atau email…" aria-label="Cari nama atau email">
        <select name="role" aria-label="Filter jabatan"><option value="">Semua jabatan</option>@foreach($roles as $role)<option value="{{ $role }}" @selected(request('role') === $role)>{{ $role }}</option>@endforeach</select>
        @if(auth()->user()->role === 'Superadmin')<select name="branch" aria-label="Filter cabang"><option value="">Semua cabang</option>@foreach($branches as $branch)<option value="{{ $branch }}" @selected(request('branch') === $branch)>{{ $branch }}</option>@endforeach</select>@endif
        <button class="outline">Tampilkan</button><a class="outline" href="{{ route('users.index') }}">Reset</a>
    </form>
    <div class="table-scroll"><table class="user-data-table"><thead><tr><th>Nama</th><th>Email</th><th>Jabatan</th><th>Cabang</th><th>Akses akun</th><th>Terdaftar</th><th>Laporan</th><th>Aksi</th></tr></thead><tbody>
    @forelse($users as $member)
    <tr>
        <td data-label="Nama"><strong>{{ $member->name }}</strong>@if($member->id === auth()->id())<small>Akun Anda</small>@endif</td>
        <td data-label="Email">{{ $member->email }}</td>
        <td data-label="Jabatan"><span class="pill">{{ $member->role }}</span></td>
        <td data-label="Cabang">{{ $member->branch ?: 'Tidak ditetapkan' }}</td>
        <td data-label="Akses akun"><span>{{ $member->must_change_password ? 'Wajib ganti kata sandi awal' : 'Kata sandi sudah diatur' }}</span><small>{{ $member->omni_id !== null ? 'SSO terhubung' : 'SSO belum terhubung' }}</small></td>
        <td data-label="Terdaftar">{{ $member->created_at ? $member->created_at->setTimezone(config('workflow.timezone'))->format('d M Y') : '—' }}</td>
        <td data-label="Laporan">@if($member->role === 'Superadmin')<span class="muted">Tidak berlaku</span>@else<a class="text-link" href="{{ route('reports.index', ['user_id' => $member->id]) }}">Lihat laporan →</a>@endif</td>
        <td data-label="Aksi">@if(auth()->user()->role === 'Superadmin' || ($member->branch === auth()->user()->branch && in_array($member->role, ['Staff', 'Marketing'], true)))<a class="text-link" href="{{ route('users.edit', $member->id) }}">Ubah / hapus</a>@else<span class="muted">—</span>@endif</td>
    </tr>
    @empty<tr><td class="user-empty" colspan="8">Tidak ada pengguna yang sesuai pencarian. Coba ubah filter atau tambah pengguna baru.</td></tr>@endforelse
    </tbody></table></div>
    <div class="pagination">{{ $users->links() }}</div>
    <p class="hint muted">{{ auth()->user()->role === 'Superadmin' ? 'Anda dapat melihat pengguna dari seluruh cabang.' : 'Daftar ini hanya memuat pengguna dalam cabang Anda.' }}</p>
</section>
@endsection
