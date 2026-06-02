<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>PlayZone - @yield('title', 'Dashboard')</title>
  <link rel="stylesheet" href="{{ asset('css/playzone.css') }}">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="/css/playzone.css">
</head>

<body>
  <aside class="sidebar">
    <div class="sb-logo">
      <div class="sb-logo-icon">
        <svg class="sb-icon-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
          <path d="M20 75 C 20 70, 80 70, 80 75 C 80 82, 20 82, 20 75 Z" fill="#FDF3E7" opacity="0.35" />
          <circle cx="27" cy="74" r="3" fill="#FFE082" />
          <circle cx="73" cy="75" r="3" fill="#81D4FA" />
          <circle cx="50" cy="77" r="3.5" fill="#FF8A80" />
          <rect x="25" y="45" width="12" height="30" rx="4" fill="#FFFFFF" />
          <rect x="63" y="45" width="12" height="30" rx="4" fill="#FFFFFF" />
          <path d="M22 45 C 22 35, 40 35, 40 45 Z" fill="#FFE082" />
          <path d="M60 45 C 60 35, 78 35, 78 45 Z" fill="#81D4FA" />
          <rect x="37" y="55" width="26" height="20" rx="2" fill="#FFFFFF" opacity="0.9" />
          <path d="M44 75 C 44 63, 56 63, 56 75 Z" fill="#E87A34" />
          <path d="M50 35 L 50 55" stroke="#FFFFFF" stroke-width="4" stroke-linecap="round" />
          <path d="M48 38 C 65 38, 75 50, 72 65 C 70 72, 58 75, 52 70" fill="none" stroke="#FDF3E7" stroke-width="7"
            stroke-linecap="round" />
          <path d="M48 38 C 65 38, 75 50, 72 65" fill="none" stroke="#FFE082" stroke-width="2" stroke-linecap="round" />
          <path d="M31 32 L 37 35 L 31 38 Z" fill="#FF8A80" />
          <line x1="31" y1="32" x2="31" y2="35" stroke="#FFFFFF" stroke-width="1.5" />
          <path d="M75 25 L 76.5 28.5 L 80 30 L 76.5 31.5 L 75 35 L 73.5 31.5 L 70 30 L 73.5 28.5 Z" fill="#FFE082" />
          <path d="M20 28 L 21 30 L 23 30.5 L 21 31 L 20 33 L 19 31 L 17 30.5 L 19 30 Z" fill="#FFFFFF" />
        </svg>
      </div>
      <div>
        <h1>PlayZone</h1>
      </div>
    </div>
    <nav class="sb-nav">
      <a href="{{ route('admin.dashboard') }}"
        class="nav-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"><svg viewBox="0 0 24 24"
          fill="none" stroke="currentColor" stroke-width="2.2">
          <rect x="3" y="3" width="7" height="7" rx="1.5" />
          <rect x="14" y="3" width="7" height="7" rx="1.5" />
          <rect x="3" y="14" width="7" height="7" rx="1.5" />
          <rect x="14" y="14" width="7" height="7" rx="1.5" />
        </svg> Dashboard</a>
      <a href="{{ route('admin.packages.index') }}"
        class="nav-item {{ request()->routeIs('admin.packages.*') ? 'active' : '' }}"><svg viewBox="0 0 24 24"
          fill="none" stroke="currentColor" stroke-width="2.2">
          <path d="M20 7H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z" />
          <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" />
        </svg> Paket Bermain</a>
      <a href="{{ route('admin.transactions.index') }}"
        class="nav-item {{ request()->routeIs('admin.transactions.*') ? 'active' : '' }}"><svg viewBox="0 0 24 24"
          fill="none" stroke="currentColor" stroke-width="2.2">
          <rect x="2" y="5" width="20" height="14" rx="2" />
          <line x1="2" y1="10" x2="22" y2="10" />
        </svg> Transaksi</a>
    </nav>
    <div class="sb-foot">
      <div class="sb-user">
        <div class="sb-ava">{{ strtoupper(substr(session('admin_name', 'A'), 0, 1)) }}</div>
        <div style="flex:1;">
          <div class="sb-uname">{{ session('admin_name', 'Admin') }}</div>
          <div class="sb-urole">Admin</div>
        </div>
        <form action="{{ route('auth.logout') }}" method="POST" id="logoutForm" style="display:inline;">@csrf<button
            type="button" class="logout-btn" onclick="openLogoutModal()"><i
              class="fas fa-right-from-bracket"></i></button></form>
      </div>
    </div>
  </aside>

  <main class="main">
    <div class="topbar" style="position:relative;">
      <div class="pg-title">
        <h2>@yield('page_title', 'Dashboard')</h2>
        <p>@yield('page_subtitle', '')</p>
      </div>
      </div>
    </div>

    <div class="content">
      @if(session('success'))
      <div class="alert as" id="flashMsg">✅ {{ session('success') }}</div> @endif
      @if(session('error'))
        <div class="alert" style="background:var(--rd-pale);color:#B03060;border:1.5px solid #F3B8C8;" id="flashMsg">⚠️
      {{ session('error') }}</div> @endif
      @yield('content')
    </div>
  </main>

  {{-- MODAL LOGOUT --}}
  <div class="mo" id="logoutModal">
    <div class="modal" style="max-width:380px;">
      <div class="delete-head">
        <div class="delete-icon-wrap"><i class="fas fa-right-from-bracket"></i></div>
        <div class="delete-title">Keluar dari akun?</div>
        <div class="delete-sub">Anda akan keluar dari sistem PlayZone.</div>
      </div>
      <div class="delete-body">
        <div style="display:flex;gap:10px;"><button type="button" class="btn btn-ou"
            style="flex:1.5;justify-content:center;" onclick="closeLogoutModal()">Batal</button><button type="button"
            class="btn btn-dg" style="flex:1.5;justify-content:center;" onclick="confirmLogout()">Ya, Logout</button>
        </div>
      </div>
    </div>
  </div>
  <script src="{{ asset('js/playzone.js') }}"></script>
  @stack('scripts')
</body>

</html>