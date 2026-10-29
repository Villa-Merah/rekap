@extends('work.layout')
@section('title', 'Tambah pengguna')
@section('subtitle', 'Buat akun pengguna baru untuk organisasi atau cabang Anda.')
@section('heading-action')
    <a class="outline" href="{{ route('accounts.index') }}">Kembali ke akun & target</a>
@endsection
@section('content')
    <section class="panel">
        <form method="post" action="{{ route('users.store') }}" class="stack-form">
            @csrf
            <label>Nama lengkap
                <input name="name" value="{{ old('name') }}" maxlength="255" autocomplete="name" required>
            </label>
            <label>Email
                <input type="email" name="email" value="{{ old('email') }}" maxlength="255" autocomplete="email" required>
            </label>
            <label>Jabatan
                <select name="role" required>
                    @foreach($manageableRoles as $role)
                        <option value="{{ $role }}" @selected(old('role') === $role)>{{ $role }}</option>
                    @endforeach
                </select>
            </label>
            @if(auth()->user()->role === 'Superadmin')
                <label>Cabang (kosongkan untuk Superadmin)
                    <input name="branch" value="{{ old('branch') }}" maxlength="255">
                </label>
            @else
                <input type="hidden" name="branch" value="{{ auth()->user()->branch }}">
                <p class="muted hint">Pengguna baru akan ditempatkan di cabang {{ auth()->user()->branch }}.</p>
            @endif
            <label>Kata sandi awal
                <input type="password" name="password" minlength="8" autocomplete="new-password" required>
                <small>Minimal 8 karakter. Sampaikan kepada pengguna melalui jalur yang aman.</small>
            </label>
            <label>Konfirmasi kata sandi
                <input type="password" name="password_confirmation" minlength="8" autocomplete="new-password" required>
            </label>
            <button class="primary">Tambah pengguna</button>
        </form>
    </section>
@endsection
