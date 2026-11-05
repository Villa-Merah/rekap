@extends('work.layout')
@section('title', 'Daftar akun')
@section('subtitle', 'Cari dan lihat akun media sosial terdaftar sesuai hak akses Anda.')
@section('heading-action')<a class="outline" href="{{ route('accounts.index') }}">Akun & target</a>@endsection
@section('content')
<section class="panel table-panel"><h2>Akun terdaftar</h2>
<form class="filter-bar user-directory-filters" method="get" action="{{ route('accounts.list') }}">
<input type="search" name="q" value="{{ request('q') }}" maxlength="100" placeholder="Cari pemilik, nama akun, atau identitas…" aria-label="Cari akun">
<select name="platform" aria-label="Filter platform"><option value="">Semua platform</option>@foreach(config('workflow.account_platforms') as $platform)<option value="{{ $platform }}" @selected(request('platform') === $platform)>{{ $platform }}</option>@endforeach</select>
<button class="outline">Tampilkan</button><a class="outline" href="{{ route('accounts.list') }}">Reset</a></form>
<div class="table-scroll"><table class="user-data-table account-list-table"><thead><tr><th>Pemilik</th><th>Cabang</th><th>Platform</th><th>Akun</th><th>Status</th></tr></thead><tbody>
@forelse($accounts as $account)<tr><td data-label="Pemilik">{{ $account->owner_name }}</td><td data-label="Cabang">{{ $account->owner_branch ?? 'Tanpa cabang' }}</td><td data-label="Platform">{{ $account->platform }}</td><td data-label="Akun"><div>{{ $account->name }}<small>{{ $account->identity }}</small>@if($account->profile_url && in_array(strtolower(parse_url($account->profile_url, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true))<a class="text-link" href="{{ $account->profile_url }}" target="_blank" rel="noopener noreferrer">Buka profil ↗</a>@endif</div></td><td data-label="Status">{{ $account->status }}</td></tr>
@empty<tr><td class="user-empty" colspan="5">Tidak ada akun yang sesuai filter.</td></tr>@endforelse
</tbody></table></div>{{ $accounts->onEachSide(1)->links() }}</section>
@endsection
