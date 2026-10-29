<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Ganti kata sandi · Rekap Buzzer</title>
    <link rel="stylesheet" href="{{ asset('communication.css') }}">
</head>
<body class="login-page">
    <main class="login-card">
        <div class="brand">r<span> Rekap Buzzer</span></div>
        <p class="eyebrow">KEAMANAN AKUN</p>
        <h1>Ganti kata sandi awal.</h1>
        <p class="muted">Buat kata sandi baru sebelum melanjutkan ke ruang kerja.</p>
        @if($errors->any())
            <div class="error" role="alert">
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <form method="post" action="{{ route('password.update') }}">
            @csrf
            @method('put')
            <label>Kata sandi saat ini
                <input name="current_password" type="password" autocomplete="current-password" required autofocus>
            </label>
            <label>Kata sandi baru
                <input name="password" type="password" minlength="8" autocomplete="new-password" required>
                <small>Gunakan minimal 8 karakter dan jangan gunakan kembali kata sandi awal.</small>
            </label>
            <label>Konfirmasi kata sandi baru
                <input name="password_confirmation" type="password" minlength="8" autocomplete="new-password" required>
            </label>
            <button class="primary" type="submit">Simpan kata sandi baru</button>
        </form>
        <form method="post" action="{{ route('logout') }}">
            @csrf
            <button class="outline" type="submit">Keluar</button>
        </form>
    </main>
</body>
</html>
