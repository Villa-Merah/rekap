@extends('work.layout')
@section('title', 'Ubah pengguna')
@section('subtitle', 'Perbarui identitas, jabatan, cabang, atau kata sandi pengguna.')
@section('heading-action')<a class="outline" href="{{ route('users.index') }}">Kembali ke data pengguna</a>@endsection
@section('content')
<section class="panel"><h2>{{ $member->name }}</h2>
<form method="post" action="{{ route('users.update', $member->id) }}" class="stack-form">@csrf @method('PUT')
<label>Nama lengkap<input name="name" value="{{ old('name', $member->name) }}" maxlength="255" required></label>
<label>Email<input type="email" name="email" value="{{ old('email', $member->email) }}" maxlength="255" required></label>
<p class="muted hint">Perubahan email akan melepas hubungan SSO. Login SIVMI berikutnya harus memakai email baru yang sama.</p>
<label>Jabatan<select name="role" required>@foreach($manageableRoles as $role)<option value="{{ $role }}" @selected(old('role', $member->role) === $role)>{{ $role }}</option>@endforeach</select></label>
@if(auth()->user()->role === 'Superadmin')<label>Cabang (kosongkan untuk Superadmin)<input name="branch" value="{{ old('branch', $member->branch) }}" maxlength="255"></label>
@else<input type="hidden" name="branch" value="{{ auth()->user()->branch }}"><p class="muted hint">Cabang: {{ auth()->user()->branch }}.</p>@endif
<label>Kata sandi baru (opsional)<input type="password" name="password" minlength="8" autocomplete="new-password"><small>Kosongkan untuk mempertahankan kata sandi saat ini. Jika diganti, pengguna wajib menggantinya saat masuk berikutnya.</small></label>
<label>Konfirmasi kata sandi baru<input type="password" name="password_confirmation" minlength="8" autocomplete="new-password"></label>
<button class="primary">Simpan perubahan</button>
</form></section>
@if($member->id !== auth()->id())
<section class="panel"><h2>Hapus pengguna</h2><p>Penghapusan bersifat permanen dan juga menghapus akun media sosial terdaftar, target akun, serta pesan pengguna ini. Pengguna yang memiliki riwayat tugas atau pemeriksaan tidak dapat dihapus.</p>
<form method="post" action="{{ route('users.destroy', $member->id) }}" class="stack-form">@csrf @method('DELETE')
<label>Ketik email {{ $member->email }} untuk mengonfirmasi<input type="email" name="confirmation" autocomplete="off" required></label><button class="outline">Hapus pengguna secara permanen</button>
</form></section>
@endif
@endsection
