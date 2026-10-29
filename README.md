# Rekap Buzzer — komunikasi tim

Implementasi saat ini mencakup login, chat pribadi, pusat notifikasi, suara opsional, jumlah belum dibaca, dan status pesan dibaca. Modul akun, kuota, tugas, bukti privat, riwayat revisi, pemeriksaan manual, audit perubahan, dan pengingat tenggat juga tersedia. Laporan PDF serta konfigurasi izin dinamis pada spesifikasi utama belum diimplementasikan.

## Menjalankan

PHP 8.3+ dan Composer diperlukan. Salin `.env.example` menjadi `.env` jika belum tersedia, sesuaikan database, lalu jalankan:

```sh
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed --class=WorkflowDemoSeeder
php artisan serve
```

Buka http://127.0.0.1:8000. Aset komunikasi disajikan langsung dari `public`, sehingga tidak membutuhkan build Node atau server WebSocket. Pada Laragon, arahkan document root ke folder `public`.

## Akun demo lokal

Kata sandi semua akun demo: `DemoBuzzer!2026`.

| Email | Peran | Cabang |
| --- | --- | --- |
| nadia@example.test | Kacab | Jakarta |
| arif@example.test | Staff | Jakarta |
| sinta@example.test | Marketing | Jakarta |
| dimas@example.test | Staff | Bandung |
| admin@example.test | Superadmin | Seluruh cabang |

Seeder hanya berjalan pada lingkungan local/testing, dan tidak mengubah akun yang sudah ada. Gunakan dua profil browser berbeda untuk menguji chat Nadia dan Arif. Klik **Aktifkan suara** pada browser penerima, kemudian kirim pesan dari pengirim.

## Perilaku dan akses

- Pembaruan setiap 5 detik selama tab terlihat. Suara hanya bekerja setelah diaktifkan pada sesi halaman tersebut; tidak ada push saat browser ditutup.
- Rekan satu cabang dapat berkomunikasi. Superadmin dapat menghubungi seluruh cabang, dan pengguna dapat membalas Superadmin. Direktur tidak otomatis memperoleh akses lintas cabang.
- Pesan hanya dapat dibaca oleh pengirim dan penerima. Konten ditampilkan sebagai teks, bukan HTML.
- Notifikasi chat dibuat bersama pesan dalam satu transaksi. Tersedia tandai sudah dibaca, pencarian rekan, dan pemuatan riwayat per 50 pesan.
- Pusat notifikasi siap menampilkan data notifikasi Laravel. Pemicu tugas baru, pengiriman bukti, permintaan revisi, dan hasil verifikasi sudah terhubung. Pengingat tenggat diproses scheduler. Kewajiban laporan PDF belum tersedia.
- Waktu pesan ditampilkan dalam Asia/Jakarta.

## Pengujian

```sh
php artisan test --compact
node --check public/communication.js
```

Pengujian meliputi login/logout, privasi percakapan, pembatasan cabang, notifikasi, status dibaca, validasi pesan, dan pagination.

## Alur tugas dan bukti

Buka `/tasks` untuk daftar tugas dan `/accounts` untuk akun serta kuota. Nadia (Kacab Jakarta) dapat membuat tugas bagi anggota cabangnya; Arif dan Sinta dapat mengirim bukti menggunakan akun pribadi yang sesuai platform. Data dari `WorkflowDemoSeeder` bersifat sintetis dan URL postingannya merupakan contoh, bukan postingan aktif.

Setiap penerima memiliki status terpisah. Pengiriman bukti mengharuskan pemilihan akun, konfirmasi seluruh aktivitas, waktu pelaksanaan, dan screenshot yang relevan. Semua screenshot pada satu pengiriman dihubungkan dengan seluruh aktivitas yang dikonfirmasi pengguna. Bukti dinilai secara manual, bukan verifikasi otomatis aktivitas di media sosial. Bukti revisi disimpan sebagai pengiriman baru.

Hanya pemilik, pemeriksa yang ditunjuk, serta pengelola cabang/Superadmin dapat membuka file bukti. Berkas berada pada disk `local` di `storage/app/private`. Batas format, ukuran, jumlah gambar, serta zona waktu diatur pada `config/workflow.php`; batas unggahan PHP/web server juga tetap berlaku.

Untuk tugas pribadi Kacab, Superadmin menetapkan atasan pemeriksa melalui halaman akun. Penetapan ini memberi izin pemeriksaan tugas terkait, tanpa memberi akses administratif lain. Tidak ada pengguna yang dapat menyetujui laporannya sendiri.

## Pengingat tenggat

Untuk pengembangan lokal, jalankan proses berikut pada terminal terpisah:

```sh
php artisan schedule:work
```

Scheduler menjalankan `tasks:remind` setiap sepuluh menit. Di server, gunakan cron atau Windows Task Scheduler untuk menjalankan `php artisan schedule:run` setiap menit. Pengingat dikirim paling banyak satu kali per penerima untuk tenggat kurang dari 24 jam dan satu kali ketika terlambat. Pengiriman manual:

```sh
php artisan tasks:remind
```

Pekerjaan yang Disetujui atau Ditolak tidak menerima pengingat tambahan. Notifikasi dan suara tersedia pada halaman tugas/akun maupun pusat komunikasi. Aktifkan suara setelah perpindahan halaman jika ingin mendengar notifikasi.
