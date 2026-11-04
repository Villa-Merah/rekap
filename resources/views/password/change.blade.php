<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Ganti kata sandi · Rekap Buzzer</title>
    <link rel="stylesheet" href="{{ asset('communication.css') }}?v={{ filemtime(public_path('communication.css')) }}">
    <link rel="stylesheet" href="{{ asset('login.css') }}?v={{ filemtime(public_path('login.css')) }}">
    <script src="{{ asset('password-visibility.js') }}" defer></script>
</head>
<body class="login-page">
    <main class="login-card">
        <div class="brand"><img class="brand-logo" src="{{ asset('logo%20VM_-4.png') }}" alt="Bimbel Gambar Villa Merah" width="84" height="84"><span>Rekap Buzzer</span></div>
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
                <span class="password-field"><input name="current_password" type="password" autocomplete="current-password" required autofocus><button class="password-toggle" type="button" aria-label="Tampilkan kata sandi" aria-pressed="false" data-password-toggle>
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
                    </button></span>
            </label>
            <label>Kata sandi baru
                <span class="password-field"><input name="password" type="password" minlength="8" autocomplete="new-password" required><button class="password-toggle" type="button" aria-label="Tampilkan kata sandi" aria-pressed="false" data-password-toggle>
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
                    </button></span>
                <small>Gunakan minimal 8 karakter dan jangan gunakan kembali kata sandi awal.</small>
            </label>
            <label>Konfirmasi kata sandi baru
                <span class="password-field"><input name="password_confirmation" type="password" minlength="8" autocomplete="new-password" required><button class="password-toggle" type="button" aria-label="Tampilkan kata sandi" aria-pressed="false" data-password-toggle>
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
                    </button></span>
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
