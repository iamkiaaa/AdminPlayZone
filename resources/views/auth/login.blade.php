<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>PlayZone - Login</title>
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="{{ asset('css/playzone.css') }}">
</head>
<body>
  <div class="lp">
    <div class="lcard">
      <div class="brand">
        <div class="brand-logo">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
            <defs><filter id="glow" x="-20%" y="-20%" width="140%" height="140%"><feDropShadow dx="0" dy="4" stdDeviation="4" flood-color="#000000" flood-opacity="0.15" /></filter></defs>
            <g>
              <path d="M20 75 C 20 70, 80 70, 80 75 C 80 82, 20 82, 20 75 Z" fill="#FDF3E7" opacity="0.3" /><circle cx="27" cy="74" r="3" fill="#FFE082" /><circle cx="73" cy="75" r="3" fill="#81D4FA" /><circle cx="50" cy="77" r="3.5" fill="#FF8A80" /><rect x="25" y="45" width="12" height="30" rx="4" fill="#FFFFFF" /><rect x="63" y="45" width="12" height="30" rx="4" fill="#FFFFFF" /><path d="M22 45 C 22 35, 40 35, 40 45 Z" fill="#FFE082" /><path d="M60 45 C 60 35, 78 35, 78 45 Z" fill="#81D4FA" /><rect x="37" y="55" width="26" height="20" rx="2" fill="#FFFFFF" opacity="0.9" /><path d="M44 75 C 44 63, 56 63, 56 75 Z" fill="#E87A34" /><path d="M50 35 L 50 55" stroke="#FFFFFF" stroke-width="4" stroke-linecap="round" /><path d="M48 38 C 65 38, 75 50, 72 65 C 70 72, 58 75, 52 70" fill="none" stroke="#FDF3E7" stroke-width="7" stroke-linecap="round" /><path d="M48 38 C 65 38, 75 50, 72 65" fill="none" stroke="#FFE082" stroke-width="2" stroke-linecap="round" /><path d="M31 32 L 37 35 L 31 38 Z" fill="#FF8A80" /><line x1="31" y1="32" x2="31" y2="35" stroke="#FFFFFF" stroke-width="1.5" /><path d="M75 25 L 76.5 28.5 L 80 30 L 76.5 31.5 L 75 35 L 73.5 31.5 L 70 30 L 73.5 28.5 Z" fill="#FFE082" /><path d="M20 28 L 21 30 L 23 30.5 L 21 31 L 20 33 L 19 31 L 17 30.5 L 19 30 Z" fill="#FFFFFF" />
            </g>
          </svg>
        </div>
        <div class="ltitle">PlayZone</div>
        <div class="lsub">Admin Login<br>Masuk untuk mengelola sistem bisnis Playground</div>
      </div>

      @if ($errors->any() || session('error'))
        <div class="lerr">
          <i class="fas fa-exclamation-circle lerr-icon"></i>
          <div><div class="lerr-title">Login gagal</div><div class="lerr-text">{{ $errors->first() ?? session('error') }}</div></div>
        </div>
      @endif

      <form action="{{ route('auth.login') }}" method="POST">
        @csrf
        <div class="fg">
          <label class="llbl" for="email">Email</label>
          <div class="input-wrap"><i class="fas fa-envelope ico"></i><input class="linp" type="email" id="email" name="email" placeholder="email@contoh.com" value="{{ old('email') }}" required autocomplete="email" autofocus></div>
        </div>
        <div class="fg">
          <label class="llbl" for="password">Kata Sandi</label>
          <div class="input-wrap">
            <i class="fas fa-lock ico"></i><input class="linp" type="password" id="password" name="password" placeholder="Masukkan kata sandi" required autocomplete="current-password">
            <button type="button" class="toggle-pwd" onclick="togglePwd()" tabindex="-1"><i class="fas fa-eye-slash" id="pwd-ico"></i></button>
          </div>
        </div>
        <button class="lbtn" type="submit"><i class="fas fa-sign-in-alt"></i> Login</button>
      </form>
    </div>
  </div>

  <script>
    function togglePwd() {
      var inp = document.getElementById('password'), ico = document.getElementById('pwd-ico');
      inp.type = (inp.type === 'password') ? 'text' : 'password';
      ico.className = (inp.type === 'password') ? 'fas fa-eye-slash' : 'fas fa-eye';
    }
  </script>
</body>
</html>