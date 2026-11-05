@extends('password.layout')
@section('title', 'Lupa kata sandi')
@section('subtitle', 'Masukkan email akun organisasi Anda untuk menerima tautan reset kata sandi.')
@section('content')
<form method="post" action="{{ route('password.email') }}">@csrf<label>Email akun<input type="email" name="email" value="{{ old('email') }}" autocomplete="email" maxlength="255" required autofocus></label><button class="primary" type="submit">Kirim tautan reset</button></form>
<p class="muted hint">Tautan berlaku selama {{ config('auth.passwords.users.expire') }} menit. Reset ini hanya mengubah kata sandi Rekap Buzzer, bukan kata sandi akun SIVMI.</p>
@endsection
