@extends('work.layout')
@section('title', 'Akun & target')
@section('subtitle', 'Daftarkan identitas akun dan pantau target 10 akun per platform untuk setiap staf.')
@section('content')
<div class="split-panels">@if(auth()->user()->role !== 'Superadmin' && !\App\Services\WorkAccess::readOnly(auth()->user()))<section class="panel"><h2>Daftarkan akun pribadi</h2><p class="muted hint">Google/Gmail dicatat sebagai identitas, terpisah dari platform aktivitas. Kata sandi media sosial tidak diperlukan.</p><form class="stack-form" method="post" action="{{ route('accounts.store') }}">@csrf<label>Platform<select name="platform">@foreach(config('workflow.account_platforms') as $platform)<option @selected(old('platform') === $platform)>{{ $platform }}</option>@endforeach</select></label><label>Nama akun<input name="name" value="{{ old('name') }}" maxlength="180" required></label><label>Username atau email<input name="identity" value="{{ old('identity') }}" maxlength="180" required></label><label>URL profil (opsional)<input type="url" name="profile_url" value="{{ old('profile_url') }}" maxlength="2000"></label><button class="primary">Daftarkan akun</button></form></section>
@endif
@if(\App\Services\WorkAccess::manages(auth()->user()))<section class="panel"><h2>Atur target akun</h2><form method="post" action="{{ route('accounts.quota') }}" class="stack-form">@csrf<label>Anggota<select name="user_id">@foreach($members as $member)<option value="{{ $member->id }}">{{ $member->name }} · {{ $member->branch }}</option>@endforeach</select></label><label>Platform<select name="platform">@foreach(config('workflow.account_platforms') as $platform)<option>{{ $platform }}</option>@endforeach</select></label><label>Target jumlah akun<input name="required_count" type="number" min="0" max="100" value="{{ config('workflow.account_target') }}" required></label><button class="primary">Simpan target</button></form>
@if(auth()->user()->role === 'Superadmin')<hr><h2>Atasan pemeriksa tugas pribadi</h2><form method="post" action="{{ route('users.supervisor') }}" class="stack-form">@csrf<label>Pengguna<select name="user_id">@foreach($members as $member)<option value="{{ $member->id }}">{{ $member->name }}</option>@endforeach</select></label><label>Atasan yang ditunjuk<select name="supervisor_id">@foreach($supervisors as $supervisor)<option value="{{ $supervisor->id }}">{{ $supervisor->name }} · {{ $supervisor->role }}</option>@endforeach</select></label><small>Penunjukan memberi izin pemeriksaan atas tugas pribadi berikutnya. Tidak memberi akses administratif tambahan.</small><button class="outline">Tetapkan pemeriksa</button></form>@endif</section>@endif</div>
<section class="panel account-targets" aria-labelledby="account-targets-title">
    <div class="account-targets-heading">
        <div><h2 id="account-targets-title">Target akun</h2><p class="muted hint">Pantau jumlah akun terdaftar dan sisa target setiap anggota per platform.</p></div>
        <span class="account-targets-count">{{ $members->count() }} anggota</span>
    </div>
    <div class="account-target-grid">
        @forelse($members as $member)
        <article class="account-target-card" aria-labelledby="account-member-{{ $member->id }}">
            <div class="account-target-member">
                <span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($member->name, 0, 1)) }}</span>
                <div><h3 id="account-member-{{ $member->id }}">{{ $member->name }}</h3><p>{{ $member->role }} · {{ $member->branch ?? 'Seluruh cabang' }}</p></div>
            </div>
            <ul class="account-platform-list">
                @foreach(config('workflow.account_platforms') as $platform)
                @php($target = (int) ($quotas->get($member->id.':'.$platform)?->required_count ?? ($member->role === 'Staff' ? config('workflow.account_target') : 0)))
                @php($registered = (int) ($counts->get($member->id.':'.$platform)?->total ?? 0))
                @php($remaining = max(0, $target - $registered))
                <li class="account-platform-row">
                    <div class="account-platform-meta"><strong>{{ $platform }}</strong><span><b>{{ $registered }}</b><span class="muted"> / {{ $target > 0 ? $target : '—' }}</span></span></div>
                    @if($target > 0)
                    <progress value="{{ min($registered, $target) }}" max="{{ $target }}" aria-label="{{ $platform }}: {{ $registered }} dari {{ $target }} akun terdaftar"></progress>
                    <span class="account-target-status {{ $remaining > 0 ? 'is-pending' : 'is-complete' }}">{{ $remaining > 0 ? 'Kurang '.$remaining.' akun' : 'Target terpenuhi' }}</span>
                    @else
                    <div class="account-target-unset" aria-hidden="true"></div><span class="account-target-status">Tanpa target</span>
                    @endif
                </li>
                @endforeach
            </ul>
        </article>
        @empty
        <p class="empty">Belum ada anggota untuk ditampilkan.</p>
        @endforelse
    </div>
</section>

<section class="panel table-panel"><h2>Akun terdaftar</h2><div class="table-scroll"><table><thead><tr><th>Pemilik</th><th>Platform</th><th>Akun</th><th>Status</th></tr></thead><tbody>@forelse($accounts as $account)<tr><td>{{ $account->owner_name }}</td><td>{{ $account->platform }}</td><td>{{ $account->name }}<small>{{ $account->identity }}</small>@if($account->profile_url)<a href="{{ $account->profile_url }}" target="_blank" rel="noopener noreferrer">Buka profil ↗</a>@endif</td><td>{{ $account->status }}</td></tr>@empty<tr><td colspan="4">Belum ada akun yang didaftarkan.</td></tr>@endforelse</tbody></table></div>{{ $accounts->links() }}</section>
@endsection
