<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Masuk · Rekap Buzzer</title>
    <link rel="stylesheet" href="{{ asset('communication.css') }}">
    <link rel="stylesheet" href="{{ asset('login.css') }}">
    <script src="{{ asset('password-visibility.js') }}" defer></script>
</head>
<body class="login-page">
    <main class="login-card">
        <div class="brand">r<span> Rekap Buzzer</span></div>
        <p class="eyebrow">RUANG KERJA ORGANISASI</p>
        <h1>Selamat datang kembali.</h1>
        <p class="muted">Masuk untuk terhubung dengan tim Anda.</p>
        <form method="post" action="{{ route('login') }}">
            @csrf
            <label>Email
                <input name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
            </label>
            <label>Kata sandi
                <span class="password-field">
                    <input name="password" type="password" autocomplete="current-password" required>
                    <button class="password-toggle" type="button" aria-label="Tampilkan kata sandi" aria-pressed="false" data-password-toggle>
                        <svg data-password-hidden-icon xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                        <svg data-password-visible-icon hidden xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="m3 3 18 18"></path>
                            <path d="M10.6 10.6a2 2 0 0 0 2.8 2.8"></path>
                            <path d="M9.9 5.2A10.7 10.7 0 0 1 12 5c6.4 0 10 7 10 7a15.7 15.7 0 0 1-3.1 3.9"></path>
                            <path d="M6.2 6.2C3.5 8 2 12 2 12s3.6 7 10 7a10.7 10.7 0 0 0 4-.8"></path>
                        </svg>
                    </button>
                </span>
            </label>
            @error('email')
                <p class="error">{{ $message }}</p>
            @enderror
            <button class="primary" type="submit">Masuk ke ruang kerja →</button>
        </form>
        <small>Akses aman untuk anggota organisasi.</small>
    </main>
</body>
</html>
