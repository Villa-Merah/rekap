<p class="nav-label">RUANG KERJA</p>
<a class="nav-link {{ request()->is('tasks*') ? 'active' : '' }}" href="{{ route('tasks.index') }}">▤ Tugas</a>
@if(\App\Services\WorkAccess::assignsTasks(auth()->user()))
<a class="nav-link {{ request()->routeIs('verifications.*') ? 'active' : '' }}" href="{{ route('verifications.index') }}">✓ Verifikasi</a>
@endif
<a class="nav-link {{ request()->routeIs('accounts.index') ? 'active' : '' }}" href="{{ route('accounts.index') }}">◎ Akun & target</a>
<a class="nav-link {{ request()->routeIs('accounts.list') ? 'active' : '' }}" href="{{ route('accounts.list') }}">▤ Daftar akun</a>
@if(\App\Services\WorkAccess::manages(auth()->user()))
<a class="nav-link {{ request()->is('users*') ? 'active' : '' }}" href="{{ route('users.index') }}">♙ Data pengguna</a>
@endif
<a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">✉ Komunikasi tim</a>
<a class="nav-link {{ request()->is('reports*') ? 'active' : '' }}" href="{{ route('reports.index') }}">▤ Laporan aktivitas</a>
<a class="nav-link {{ request()->routeIs('activity-logs.index') ? 'active' : '' }}" href="{{ route('activity-logs.index') }}">◷ Log aktivitas</a>
<a class="nav-link {{ request()->routeIs('guide') ? 'active' : '' }}" href="{{ route('guide') }}">? Panduan penggunaan</a>
