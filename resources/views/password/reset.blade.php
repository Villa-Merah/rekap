@extends('password.layout')
@section('title', 'Buat kata sandi baru')
@section('subtitle', 'Gunakan kata sandi minimal 8 karakter untuk akun Rekap Buzzer Anda.')
@section('content')
<form method="post" action="{{ route('password.store') }}">@csrf<input type="hidden" name="token" value="{{ $token }}">
<label>Email akun<input type="email" name="email" value="{{ old('email', $email) }}" autocomplete="username" maxlength="255" required></label>
<label>Kata sandi baru<span class="password-field"><input name="password" type="password" autocomplete="new-password" minlength="8" required>@include('password.toggle')</span></label>
<label>Konfirmasi kata sandi baru<span class="password-field"><input name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required>@include('password.toggle')</span></label>
<button class="primary" type="submit">Simpan kata sandi baru</button></form>
@endsection
