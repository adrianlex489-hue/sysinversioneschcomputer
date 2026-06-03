<?php
session_start();
if (isset($_SESSION['id_usuario'])) { header("Location: dashboard.php"); exit; }
require_once '../conf/database.php';
$conexion = $pdo;
$error_message = '';

// Detectar si viene de un cierre de sesión
$swal_logout = null;
if (isset($_COOKIE['swal_logout'])) {
    $data = json_decode($_COOKIE['swal_logout'], true);
    if (is_array($data)) $swal_logout = $data;
    // Eliminar la cookie inmediatamente
    setcookie('swal_logout', '', time() - 3600, '/');
}
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username === '' || $password === '') {
        $error_message = "Por favor, ingrese su usuario y contraseña.";
    } else {
        try {
            $stmt = $conexion->prepare("SELECT id_usuario,username,clave,id_rol,nombre_completo,estado FROM usuarios WHERE username=:u AND estado=1 LIMIT 1");
            $stmt->execute([':u' => $username]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($usuario && password_verify($password, $usuario['clave'])) {
                // Regenerar ID sin eliminar la sesión anterior (más seguro en XAMPP)
                session_regenerate_id(false);
                $_SESSION['id_usuario']      = $usuario['id_usuario'];
                $_SESSION['username']        = $usuario['username'];
                $_SESSION['id_rol']          = $usuario['id_rol'];
                $_SESSION['nombre_completo'] = $usuario['nombre_completo'];
                // Alerta de bienvenida al llegar al dashboard
                $_SESSION['swal_login'] = [
                    'nombre' => $usuario['nombre_completo'],
                    'rol'    => $usuario['id_rol'],
                ];
                session_write_close(); // forzar escritura antes del redirect
                header("Location: dashboard.php"); exit;
            } else {
                $error_message = "Credenciales inválidas. Verifique su usuario y contraseña.";
            }
        } catch (PDOException $e) {
            error_log("Error BD login: " . $e->getMessage());
            $error_message = "Error interno del servidor. Intente más tarde.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SysInversiones CH Computer : Acceso al Sistema</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
  --azul:       #1a3a6b;
  --azul-claro: #2563eb;
  --azul-medio: #1e40af;
  --celeste:    #0ea5e9;
  --oscuro:     #0f172a;
  --blanco:     #ffffff;
}
html,body{height:100%;font-family:'Inter',sans-serif;overflow:hidden;}

/* -- WRAPPER -- */
.login-wrapper{display:flex;height:100vh;width:100vw;}

/* -- PANEL IZQUIERDO -- */
.panel-left{
  flex:1;position:relative;overflow:hidden;
  background:linear-gradient(160deg,#0f172a 0%,#1a3a6b 45%,#1e40af 80%,#2563eb 100%);
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  padding:48px 40px;
}

/* Grid tech de fondo */
.tech-grid{
  position:absolute;inset:0;
  background-image:
    linear-gradient(rgba(37,99,235,.12) 1px, transparent 1px),
    linear-gradient(90deg, rgba(37,99,235,.12) 1px, transparent 1px);
  background-size:40px 40px;
  animation:gridMove 20s linear infinite;
}
@keyframes gridMove{from{background-position:0 0;}to{background-position:40px 40px;}}

/* Orbes */
.orb{
  position:absolute;border-radius:50%;
  background:radial-gradient(circle,rgba(37,99,235,.25),transparent 70%);
  animation:orbPulse ease-in-out infinite;
}
.orb1{width:500px;height:500px;top:-150px;left:-150px;animation-duration:9s;}
.orb2{width:380px;height:380px;bottom:-100px;right:-80px;animation-duration:12s;animation-delay:2s;}
.orb3{width:220px;height:220px;top:40%;left:55%;animation-duration:7s;animation-delay:1s;}
@keyframes orbPulse{0%,100%{transform:scale(1);opacity:.5;}50%{transform:scale(1.2);opacity:1;}}

/* Partículas */
.particle{
  position:absolute;border-radius:50%;
  background:rgba(147,197,253,.2);
  animation:floatUp linear infinite;
}
@keyframes floatUp{
  0%{transform:translateY(0) rotate(0deg) scale(1);opacity:.6;}
  100%{transform:translateY(-110vh) rotate(720deg) scale(.2);opacity:0;}
}

/* Iconos tech flotantes */
.float-icon{
  position:absolute;color:rgba(147,197,253,.12);
  animation:floatIcon ease-in-out infinite;
}
@keyframes floatIcon{
  0%,100%{transform:translateY(0) rotate(-4deg);}
  50%{transform:translateY(-16px) rotate(4deg);}
}

/* Líneas de circuito animadas */
.circuit-line{
  position:absolute;background:rgba(37,99,235,.2);
  animation:circuitPulse 4s ease-in-out infinite;
}
@keyframes circuitPulse{0%,100%{opacity:.2;}50%{opacity:.6;}}

/* Branding */
.brand-logo{position:relative;z-index:2;text-align:center;margin-bottom:36px;animation:fadeSlideDown .8s ease both;}
@keyframes fadeSlideDown{from{opacity:0;transform:translateY(-30px);}to{opacity:1;transform:translateY(0);}}

.brand-logo .logo-img-wrap{
  position:relative;width:130px;height:130px;margin:0 auto 18px;
}
.brand-logo .logo-img-wrap .ring{
  position:absolute;inset:-10px;border-radius:50%;
  border:2px solid rgba(147,197,253,.3);
  animation:ringRotate 10s linear infinite;
}
.brand-logo .logo-img-wrap .ring2{
  position:absolute;inset:-22px;border-radius:50%;
  border:1px dashed rgba(147,197,253,.15);
  animation:ringRotate 18s linear infinite reverse;
}
@keyframes ringRotate{to{transform:rotate(360deg);}}

.brand-logo .logo-img-wrap img{
  width:130px;height:130px;object-fit:cover;
  border-radius:50%;
  border:4px solid rgba(147,197,253,.35);
  box-shadow:0 12px 40px rgba(0,0,0,.4),0 0 0 8px rgba(37,99,235,.15);
  display:block;
  animation:logoPulse 3s ease-in-out infinite;
}
@keyframes logoPulse{
  0%,100%{box-shadow:0 12px 40px rgba(0,0,0,.4),0 0 0 8px rgba(37,99,235,.15);}
  50%{box-shadow:0 16px 50px rgba(0,0,0,.5),0 0 0 16px rgba(37,99,235,.22);}
}

.brand-logo h1{
  color:#fff;font-size:1.7rem;font-weight:800;
  letter-spacing:1.5px;line-height:1.1;
}
.brand-logo h1 span{color:#93c5fd;font-weight:300;font-size:1rem;display:block;letter-spacing:3px;margin-top:2px;}
.brand-logo p{color:rgba(255,255,255,.55);font-size:.8rem;margin-top:8px;letter-spacing:.5px;}

/* Features */
.features{position:relative;z-index:2;width:100%;max-width:340px;display:flex;flex-direction:column;gap:11px;}
.feature-item{
  display:flex;align-items:center;gap:14px;
  background:rgba(255,255,255,.06);
  border:1px solid rgba(147,197,253,.15);
  border-radius:12px;padding:12px 15px;
  backdrop-filter:blur(8px);
  transform:translateX(-40px);opacity:0;
  animation:slideInLeft .6s ease forwards;
  transition:transform .25s,background .25s,border-color .25s;
}
.feature-item:hover{transform:translateX(5px)!important;background:rgba(255,255,255,.12);border-color:rgba(147,197,253,.35);}
.feature-item:nth-child(1){animation-delay:.3s;}
.feature-item:nth-child(2){animation-delay:.45s;}
.feature-item:nth-child(3){animation-delay:.6s;}
.feature-item:nth-child(4){animation-delay:.75s;}
@keyframes slideInLeft{to{transform:translateX(0);opacity:1;}}

.fi-icon{
  width:38px;height:38px;border-radius:10px;flex-shrink:0;
  background:rgba(37,99,235,.35);
  display:flex;align-items:center;justify-content:center;
  border:1px solid rgba(147,197,253,.2);
}
.fi-icon i{color:#93c5fd;font-size:.95rem;}
.fi-text strong{display:block;color:#fff;font-size:.84rem;font-weight:600;}
.fi-text span{color:rgba(255,255,255,.5);font-size:.73rem;}

.panel-left-footer{
  position:absolute;bottom:16px;left:0;right:0;
  text-align:center;color:rgba(255,255,255,.3);font-size:.72rem;z-index:2;
}

/* -- PANEL DERECHO -- */
.panel-right{
  width:440px;flex-shrink:0;background:#fff;
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  padding:48px 44px;position:relative;
  box-shadow:-12px 0 60px rgba(0,0,0,.18);
  animation:panelSlideIn .7s cubic-bezier(.22,1,.36,1) both;
}
@keyframes panelSlideIn{from{transform:translateX(60px);opacity:0;}to{transform:translateX(0);opacity:1;}}

/* Línea decorativa superior */
.panel-right::before{
  content:'';position:absolute;top:0;left:0;right:0;height:4px;
  background:linear-gradient(90deg,#0f172a,#1a3a6b,#2563eb,#0ea5e9,#2563eb,#1a3a6b);
  background-size:300% 100%;
  animation:gradientSlide 4s ease infinite;
}
@keyframes gradientSlide{0%,100%{background-position:0% 50%;}50%{background-position:100% 50%;}}

/* Header formulario */
.form-header{text-align:center;margin-bottom:28px;width:100%;animation:fadeUp .6s .2s ease both;}
@keyframes fadeUp{from{opacity:0;transform:translateY(20px);}to{opacity:1;transform:translateY(0);}}

.form-header .avatar{
  width:68px;height:68px;margin:0 auto 14px;position:relative;
  background:linear-gradient(135deg,#1a3a6b,#2563eb);
  border-radius:18px;
  display:flex;align-items:center;justify-content:center;
  box-shadow:0 8px 24px rgba(37,99,235,.35);
  animation:avatarBounce 2.5s 1s ease infinite;
}
@keyframes avatarBounce{0%,100%{transform:translateY(0);}50%{transform:translateY(-5px);}}
.form-header .avatar i{color:#fff;font-size:1.7rem;}
.form-header .avatar .pulse-ring{
  position:absolute;inset:-6px;border-radius:24px;
  border:2px solid rgba(37,99,235,.4);
  animation:pulseRing 2.5s 1s ease-out infinite;
}
@keyframes pulseRing{0%{transform:scale(1);opacity:.8;}100%{transform:scale(1.3);opacity:0;}}

.form-header h2{font-size:1.4rem;font-weight:700;color:#111827;margin-bottom:4px;}
.form-header p{color:#6b7280;font-size:.84rem;}

/* Campos */
.field-group{width:100%;margin-bottom:15px;animation:fadeUp .5s ease both;}
.field-group:nth-child(1){animation-delay:.35s;}
.field-group:nth-child(2){animation-delay:.45s;}

.field-group label{
  display:block;font-size:.76rem;font-weight:600;
  color:#374151;margin-bottom:6px;
  text-transform:uppercase;letter-spacing:.5px;
}
.field-wrap{position:relative;}
.field-icon{
  position:absolute;left:14px;top:50%;transform:translateY(-50%);
  color:#9ca3af;font-size:.88rem;pointer-events:none;
  transition:color .25s,transform .25s;
}
.field-wrap input{
  width:100%;padding:12px 44px 12px 42px;
  border:1.5px solid #e5e7eb;border-radius:10px;
  font-size:.92rem;font-family:'Inter',sans-serif;
  color:#111827;background:#f8fafc;
  transition:border-color .25s,box-shadow .25s,background .25s,transform .15s;
  outline:none;
}
.field-wrap input:focus{
  border-color:#2563eb;background:#fff;
  box-shadow:0 0 0 3px rgba(37,99,235,.12);
  transform:translateY(-1px);
}
.field-wrap:focus-within .field-icon{color:#2563eb;transform:translateY(-50%) scale(1.15);}

.btn-toggle-pass{
  position:absolute;right:12px;top:50%;transform:translateY(-50%);
  background:none;border:none;cursor:pointer;
  color:#9ca3af;font-size:.88rem;padding:4px;
  transition:color .2s,transform .2s;
}
.btn-toggle-pass:hover{color:#2563eb;transform:translateY(-50%) scale(1.2);}

/* Recordarme */
.remember-row{
  display:flex;align-items:center;justify-content:space-between;
  width:100%;margin-bottom:20px;
  animation:fadeUp .5s .55s ease both;
}
.remember-label{
  display:flex;align-items:center;gap:8px;cursor:pointer;
  font-size:.83rem;color:#374151;user-select:none;
}
.remember-label input[type="checkbox"]{
  width:16px;height:16px;accent-color:#2563eb;cursor:pointer;
}

/* Botón submit */
.btn-login{
  width:100%;padding:13px;
  background:linear-gradient(135deg,#1a3a6b 0%,#2563eb 100%);
  color:#fff;border:none;border-radius:10px;
  font-size:.95rem;font-weight:700;font-family:'Inter',sans-serif;
  cursor:pointer;letter-spacing:.3px;
  display:flex;align-items:center;justify-content:center;gap:8px;
  box-shadow:0 4px 18px rgba(37,99,235,.35);
  transition:transform .2s,box-shadow .2s;
  position:relative;overflow:hidden;
  animation:fadeUp .5s .6s ease both;
}
.btn-login::after{
  content:'';position:absolute;
  top:0;left:-100%;width:60%;height:100%;
  background:linear-gradient(90deg,transparent,rgba(255,255,255,.2),transparent);
  transform:skewX(-20deg);
  animation:shimmer 3.5s 2s ease infinite;
}
@keyframes shimmer{0%{left:-100%;}100%{left:200%;}}
.btn-login:hover{transform:translateY(-2px);box-shadow:0 10px 30px rgba(37,99,235,.45);}
.btn-login:active{transform:translateY(0);}
.btn-login.loading{opacity:.75;pointer-events:none;}
.btn-login .spinner{
  display:none;width:18px;height:18px;
  border:2px solid rgba(255,255,255,.4);
  border-top-color:#fff;border-radius:50%;
  animation:spin .7s linear infinite;
}
@keyframes spin{to{transform:rotate(360deg);}}

/* Alerta error */
.alert-error{
  width:100%;background:#fef2f2;
  border:1px solid #fecaca;border-left:4px solid #e74c3c;
  border-radius:10px;padding:12px 16px;margin-bottom:16px;
  display:flex;align-items:flex-start;gap:10px;
  animation:shakeIn .5s ease both;
}
@keyframes shakeIn{
  0%{transform:translateX(-8px);opacity:0;}
  20%{transform:translateX(8px);}
  40%{transform:translateX(-5px);}
  60%{transform:translateX(5px);}
  80%{transform:translateX(-2px);}
  100%{transform:translateX(0);opacity:1;}
}
.alert-error i{color:#e74c3c;font-size:1rem;margin-top:1px;flex-shrink:0;}
.alert-error span{color:#7f1d1d;font-size:.84rem;line-height:1.4;}

/* Divisor */
.divider{
  width:100%;display:flex;align-items:center;gap:12px;
  margin:16px 0 12px;color:#d1d5db;font-size:.73rem;
  animation:fadeUp .5s .65s ease both;
}
.divider::before,.divider::after{content:'';flex:1;height:1px;background:#e5e7eb;}

/* Footer */
.form-footer{
  text-align:center;color:#9ca3af;font-size:.75rem;width:100%;
  animation:fadeUp .5s .7s ease both;
}
.form-footer strong{color:#1a3a6b;}

/* Responsive */
@media(max-width:768px){
  .panel-left{display:none;}
  .panel-right{width:100%;padding:36px 28px;box-shadow:none;}
}
</style>
</head>
<body>
<div class="login-wrapper">

<!-- -- PANEL IZQUIERDO -- -->
<div class="panel-left">
  <!-- Grid tech -->
  <div class="tech-grid"></div>

  <!-- Orbes -->
  <div class="orb orb1"></div>
  <div class="orb orb2"></div>
  <div class="orb orb3"></div>

  <!-- Partículas -->
  <div class="particle" style="width:8px;height:8px;left:6%;bottom:-10px;animation-duration:15s;animation-delay:0s;"></div>
  <div class="particle" style="width:5px;height:5px;left:18%;bottom:-10px;animation-duration:11s;animation-delay:2s;"></div>
  <div class="particle" style="width:12px;height:12px;left:35%;bottom:-10px;animation-duration:19s;animation-delay:1s;"></div>
  <div class="particle" style="width:7px;height:7px;left:52%;bottom:-10px;animation-duration:13s;animation-delay:3.5s;"></div>
  <div class="particle" style="width:10px;height:10px;left:70%;bottom:-10px;animation-duration:17s;animation-delay:1.5s;"></div>
  <div class="particle" style="width:6px;height:6px;left:86%;bottom:-10px;animation-duration:10s;animation-delay:4s;"></div>

  <!-- Iconos tech flotantes -->
  <i class="fas fa-laptop float-icon" style="top:10%;left:7%;animation-duration:5s;font-size:2.8rem;"></i>
  <i class="fas fa-microchip float-icon" style="top:22%;right:9%;animation-duration:7s;animation-delay:1s;font-size:2rem;"></i>
  <i class="fas fa-tools float-icon" style="bottom:32%;left:5%;animation-duration:6s;animation-delay:2s;font-size:2.2rem;"></i>
  <i class="fas fa-server float-icon" style="bottom:16%;right:7%;animation-duration:8s;animation-delay:.5s;font-size:1.8rem;"></i>
  <i class="fas fa-wifi float-icon" style="top:52%;left:14%;animation-duration:9s;animation-delay:3s;font-size:1.6rem;"></i>
  <i class="fas fa-hdd float-icon" style="top:68%;right:15%;animation-duration:6.5s;animation-delay:1.5s;font-size:1.5rem;"></i>

  <!-- Branding -->
  <div class="brand-logo">
    <div class="logo-img-wrap">
      <div class="ring"></div>
      <div class="ring2"></div>
      <img src="/sysinversioneschcomputer/Logo/logo.jpg" alt="SysInversiones CH Computer">
    </div>
    <h1>INVERSIONES<span>CH COMPUTER SRL</span></h1>
    <p><i class="fas fa-map-marker-alt" style="margin-right:4px;"></i>Chiclayo, Lambayeque , Perú</p>
  </div>

  <!-- Features -->
  <div class="features">
    <div class="feature-item">
      <div class="fi-icon"><i class="fas fa-tools"></i></div>
      <div class="fi-text">
        <strong>Servicio Técnico</strong>
        <span>Gestión de órdenes y reparaciones en tiempo real</span>
      </div>
    </div>
    <div class="feature-item">
      <div class="fi-icon"><i class="fas fa-laptop"></i></div>
      <div class="fi-text">
        <strong>Venta de Equipos</strong>
        <span>Laptops, accesorios y periféricos tecnológicos</span>
      </div>
    </div>
    <div class="feature-item">
      <div class="fi-icon"><i class="fas fa-boxes"></i></div>
      <div class="fi-text">
        <strong>Control de Inventario</strong>
        <span>Stock de productos y repuestos actualizado</span>
      </div>
    </div>
    <div class="feature-item">
      <div class="fi-icon"><i class="fas fa-chart-line"></i></div>
      <div class="fi-text">
        <strong>Reportes y Estadísticas</strong>
        <span>Dashboard con métricas clave del negocio</span>
      </div>
    </div>
  </div>

  <div class="panel-left-footer">
    &copy; <?php echo date('Y'); ?> SysInversiones CH Computer &mdash; Todos los derechos reservados
  </div>
</div>

<!-- -- PANEL DERECHO -- -->
<div class="panel-right">

  <div class="form-header">
    <div class="avatar">
      <div class="pulse-ring"></div>
      <i class="fas fa-user-shield"></i>
    </div>
    <h2>Bienvenido de vuelta</h2>
    <p>Ingresa tus credenciales para acceder al sistema</p>
  </div>

  <?php if (!empty($error_message)): ?>
  <div class="alert-error" id="alertError">
    <i class="fas fa-exclamation-circle"></i>
    <span><?php echo htmlspecialchars($error_message); ?></span>
  </div>
  <?php endif; ?>

  <form action="login.php" method="POST" id="loginForm" style="width:100%;" autocomplete="off">

    <div class="field-group">
      <label for="username"><i class="fas fa-user" style="margin-right:5px;"></i>Usuario</label>
      <div class="field-wrap">
        <i class="fas fa-user field-icon"></i>
        <input type="text" id="username" name="username"
               placeholder="Ingresa tu usuario"
               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
               required autofocus autocomplete="username">
      </div>
    </div>

    <div class="field-group">
      <label for="password"><i class="fas fa-lock" style="margin-right:5px;"></i>Contraseña</label>
      <div class="field-wrap">
        <i class="fas fa-lock field-icon"></i>
        <input type="password" id="password" name="password"
               placeholder="Ingresa tu contraseña"
               required autocomplete="current-password">
        <button type="button" class="btn-toggle-pass" id="togglePass" title="Mostrar/ocultar">
          <i class="fas fa-eye" id="eyeIcon"></i>
        </button>
      </div>
    </div>

    <div class="remember-row">
      <label class="remember-label">
        <input type="checkbox" id="remember" name="remember">
        Recordar sesión
      </label>
    </div>

    <button type="submit" class="btn-login" id="btnLogin">
      <div class="spinner" id="loginSpinner"></div>
      <i class="fas fa-sign-in-alt" id="loginIcon"></i>
      <span id="loginText">Ingresar al Sistema</span>
    </button>

  </form>

  <div class="divider">
    <i class="fas fa-shield-alt" style="color:#2563eb;margin-right:4px;"></i>Acceso seguro
  </div>

  <div class="form-footer">
    <strong>SysInversiones CH Computer</strong><br>
    <span style="margin-top:4px;display:block;">
      <i class="fas fa-tools" style="margin-right:3px;color:#2563eb;"></i>Servicio Técnico &amp; Ventas
      &nbsp;|&nbsp;
      <i class="fas fa-lock" style="margin-right:3px;color:#2563eb;"></i>Conexión cifrada
    </span>
  </div>

</div>
</div>

<script>
// Toggle password
document.getElementById('togglePass').addEventListener('click', function(){
  var inp  = document.getElementById('password');
  var icon = document.getElementById('eyeIcon');
  if(inp.type==='password'){
    inp.type='text';
    icon.classList.replace('fa-eye','fa-eye-slash');
  } else {
    inp.type='password';
    icon.classList.replace('fa-eye-slash','fa-eye');
  }
});

// Spinner al enviar
document.getElementById('loginForm').addEventListener('submit', function(){
  var btn     = document.getElementById('btnLogin');
  var spinner = document.getElementById('loginSpinner');
  var icon    = document.getElementById('loginIcon');
  var text    = document.getElementById('loginText');
  btn.classList.add('loading');
  spinner.style.display = 'block';
  icon.style.display    = 'none';
  text.textContent      = 'Verificando...';
});

// Auto-cerrar alerta de error
var alertEl = document.getElementById('alertError');
if(alertEl){
  setTimeout(function(){
    alertEl.style.transition = 'opacity .4s, transform .4s';
    alertEl.style.opacity    = '0';
    alertEl.style.transform  = 'translateX(20px)';
    setTimeout(function(){ alertEl.remove(); }, 400);
  }, 5000);
}

// Efecto ripple en el botón
document.getElementById('btnLogin').addEventListener('mousedown', function(e){
  var btn  = this;
  var rect = btn.getBoundingClientRect();
  var x    = e.clientX - rect.left;
  var y    = e.clientY - rect.top;
  var rip  = document.createElement('span');
  rip.style.cssText =
    'position:absolute;border-radius:50%;background:rgba(255,255,255,.3);' +
    'width:0;height:0;left:' + x + 'px;top:' + y + 'px;' +
    'transform:translate(-50%,-50%);' +
    'animation:ripple .6s ease-out forwards;pointer-events:none;';
  btn.appendChild(rip);
  setTimeout(function(){ rip.remove(); }, 600);
});

// Keyframe ripple
var st = document.createElement('style');
st.textContent = '@keyframes ripple{to{width:300px;height:300px;opacity:0;}}';
document.head.appendChild(st);
</script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {

    <?php if ($swal_logout): ?>
    // ── Alerta de cierre de sesión dinámica ──────────────────────────────────
    (function(){
        var nombre = '<?= addslashes(htmlspecialchars($swal_logout['nombre'] ?? '')) ?>';

        var ks = document.createElement('style');
        ks.textContent =
            '@keyframes loOvIn{from{opacity:0}to{opacity:1}}' +
            '@keyframes loOvOut{from{opacity:1}to{opacity:0}}' +
            '@keyframes loCardIn{from{opacity:0;transform:scale(.85) translateY(30px)}to{opacity:1;transform:scale(1) translateY(0)}}' +
            '@keyframes loCardOut{from{opacity:1;transform:scale(1)}to{opacity:0;transform:scale(.9) translateY(-20px)}}' +
            '@keyframes loIconPop{0%{transform:scale(0) rotate(-180deg)}70%{transform:scale(1.2) rotate(10deg)}100%{transform:scale(1) rotate(0)}}' +
            '@keyframes loFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-5px)}}' +
            '@keyframes loFadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}' +
            '@keyframes loShimmer{0%{left:-100%}100%{left:200%}}' +
            '@keyframes loPulse{0%,100%{box-shadow:0 0 0 0 rgba(14,165,233,.4)}70%{box-shadow:0 0 0 14px rgba(14,165,233,0)}}';
        document.head.appendChild(ks);

        // Overlay
        var ov = document.createElement('div');
        ov.style.cssText = 'position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;background:rgba(8,16,40,.78);backdrop-filter:blur(8px);animation:loOvIn .3s ease both;';

        // Card
        var card = document.createElement('div');
        card.style.cssText = 'position:relative;width:400px;max-width:94vw;background:#fff;border-radius:26px;overflow:hidden;box-shadow:0 40px 100px rgba(0,0,0,.4);animation:loCardIn .5s cubic-bezier(.22,1,.36,1) .08s both;';

        // Header azul oscuro → cyan
        var hdr = document.createElement('div');
        hdr.style.cssText = 'background:linear-gradient(135deg,#0c1a3a,#0f3460,#0ea5e9);padding:30px 28px 22px;text-align:center;position:relative;overflow:hidden;';

        // Canvas partículas
        var cv = document.createElement('canvas');
        cv.width=400; cv.height=140;
        cv.style.cssText='position:absolute;inset:0;width:100%;height:100%;pointer-events:none;opacity:.6;';
        hdr.appendChild(cv);

        // Icono de logout con animación
        var iw = document.createElement('div');
        iw.style.cssText='position:relative;z-index:2;width:80px;height:80px;margin:0 auto 14px;animation:loFloat 3s ease-in-out infinite;';
        iw.innerHTML=
            '<div style="position:absolute;inset:-10px;border-radius:50%;border:2px solid rgba(255,255,255,.25);animation:loFloat 2s ease-in-out infinite;"></div>'+
            '<div style="width:80px;height:80px;border-radius:50%;background:rgba(255,255,255,.14);border:3px solid rgba(255,255,255,.4);display:flex;align-items:center;justify-content:center;box-shadow:0 8px 24px rgba(0,0,0,.25);animation:loIconPop .6s .2s cubic-bezier(.22,1,.36,1) both,loPulse 2s 1s ease-in-out infinite;">'+
            '<i class="fas fa-sign-out-alt" style="color:#fff;font-size:1.9rem;"></i></div>';

        var t1 = document.createElement('div');
        t1.style.cssText='position:relative;z-index:2;color:rgba(255,255,255,.75);font-size:.75rem;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;margin-bottom:5px;';
        t1.textContent='Sesión finalizada';

        var t2 = document.createElement('div');
        t2.style.cssText='position:relative;z-index:2;color:#fff;font-size:1.3rem;font-weight:800;';
        t2.textContent='¡Hasta pronto!';

        var nameBadge = document.createElement('div');
        nameBadge.style.cssText='position:relative;z-index:2;display:inline-flex;align-items:center;gap:7px;margin-top:9px;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.28);border-radius:20px;padding:4px 16px;font-size:.78rem;font-weight:700;color:#fff;backdrop-filter:blur(4px);';
        nameBadge.innerHTML='<i class="fas fa-user"></i> '+nombre;

        hdr.appendChild(iw); hdr.appendChild(t1); hdr.appendChild(t2); hdr.appendChild(nameBadge);

        // Body
        var body = document.createElement('div');
        body.style.cssText='padding:22px 26px 14px;';

        // Mensaje
        var msg = document.createElement('div');
        msg.style.cssText='display:flex;align-items:center;gap:12px;background:linear-gradient(135deg,#f0f9ff,#e0f2fe);border-radius:14px;padding:14px 16px;margin-bottom:14px;border-left:4px solid #0ea5e9;animation:loFadeUp .4s .35s ease both;opacity:0;animation-fill-mode:both;';
        msg.innerHTML=
            '<div style="width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg,#0c4a6e,#0ea5e9);display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 4px 12px rgba(14,165,233,.3);">'+
            '<i class="fas fa-shield-check" style="color:#fff;font-size:.9rem;"></i></div>'+
            '<div><div style="font-size:.7rem;font-weight:700;color:#0369a1;text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px;">Seguridad</div>'+
            '<div style="font-size:.85rem;color:#0c4a6e;font-weight:600;">Tu sesión fue cerrada de forma segura</div></div>';

        // Hora de cierre
        var timeRow = document.createElement('div');
        timeRow.style.cssText='display:flex;gap:10px;margin-bottom:14px;animation:loFadeUp .4s .45s ease both;opacity:0;animation-fill-mode:both;';
        var now = new Date();
        var hStr = String(now.getHours()).padStart(2,'0')+':'+String(now.getMinutes()).padStart(2,'0');
        var dStr = String(now.getDate()).padStart(2,'0')+'/'+(String(now.getMonth()+1).padStart(2,'0'))+'/'+now.getFullYear();
        timeRow.innerHTML=
            '<div style="flex:1;background:#f8fafc;border-radius:12px;padding:10px 14px;border:1px solid #e2e8f0;text-align:center;">'+
            '<div style="font-size:.65rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px;"><i class="fas fa-calendar mr-1" style="color:#0ea5e9;"></i>Fecha</div>'+
            '<div style="font-size:.9rem;font-weight:700;color:#1e293b;">'+dStr+'</div></div>'+
            '<div style="flex:1;background:#f8fafc;border-radius:12px;padding:10px 14px;border:1px solid #e2e8f0;text-align:center;">'+
            '<div style="font-size:.65rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px;"><i class="fas fa-clock mr-1" style="color:#0ea5e9;"></i>Hora de cierre</div>'+
            '<div style="font-size:.9rem;font-weight:700;color:#1e293b;font-family:monospace;">'+hStr+'</div></div>';

        // Barra de progreso
        var progRow = document.createElement('div');
        progRow.style.cssText='animation:loFadeUp .4s .55s ease both;opacity:0;animation-fill-mode:both;';
        progRow.innerHTML=
            '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:5px;">'+
            '<span style="font-size:.7rem;color:#94a3b8;font-weight:600;"><i class="fas fa-hourglass-half mr-1"></i>Redirigiendo al login</span>'+
            '<span id="loCd" style="font-size:.72rem;font-weight:800;color:#0ea5e9;background:#e0f2fe;border-radius:20px;padding:2px 9px;">5s</span></div>'+
            '<div style="height:5px;background:#e2e8f0;border-radius:99px;overflow:hidden;">'+
            '<div id="loBar" style="height:100%;width:100%;background:linear-gradient(90deg,#0c1a3a,#0ea5e9);border-radius:99px;transition:width .1s linear;position:relative;overflow:hidden;">'+
            '<div style="position:absolute;top:0;left:-100%;width:50%;height:100%;background:rgba(255,255,255,.35);transform:skewX(-20deg);animation:loShimmer 1.5s ease infinite;"></div>'+
            '</div></div>';

        body.appendChild(msg); body.appendChild(timeRow); body.appendChild(progRow);

        // Footer
        var ftr = document.createElement('div');
        ftr.style.cssText='padding:0 26px 24px;animation:loFadeUp .4s .6s ease both;opacity:0;animation-fill-mode:both;';
        var btn = document.createElement('button');
        btn.style.cssText='width:100%;padding:13px;border:none;border-radius:14px;cursor:pointer;background:linear-gradient(135deg,#0c1a3a,#0ea5e9);color:#fff;font-size:.92rem;font-weight:800;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:9px;box-shadow:0 6px 20px rgba(14,165,233,.35);transition:transform .2s,box-shadow .2s;position:relative;overflow:hidden;';
        btn.innerHTML='<i class="fas fa-sign-in-alt"></i> Volver a iniciar sesión';
        btn.onmouseover=function(){this.style.transform='translateY(-2px)';this.style.boxShadow='0 10px 28px rgba(14,165,233,.5)';};
        btn.onmouseout=function(){this.style.transform='';this.style.boxShadow='0 6px 20px rgba(14,165,233,.35)';};
        ftr.appendChild(btn);

        card.appendChild(hdr); card.appendChild(body); card.appendChild(ftr);
        ov.appendChild(card);
        document.body.appendChild(ov);

        // Partículas
        var ctx2=cv.getContext('2d');
        var pts=[];
        for(var i=0;i<30;i++) pts.push({x:Math.random()*400,y:Math.random()*140,r:Math.random()*2+.5,dx:(Math.random()-.5)*.6,dy:(Math.random()-.5)*.6,o:Math.random()*.4+.08});
        function drawPts(){
            ctx2.clearRect(0,0,400,140);
            pts.forEach(function(p){
                ctx2.beginPath();ctx2.arc(p.x,p.y,p.r,0,Math.PI*2);
                ctx2.fillStyle='rgba(255,255,255,'+p.o+')';ctx2.fill();
                p.x+=p.dx;p.y+=p.dy;
                if(p.x<0||p.x>400)p.dx*=-1;
                if(p.y<0||p.y>140)p.dy*=-1;
            });
            requestAnimationFrame(drawPts);
        }
        drawPts();

        // Countdown
        var total=5000,elapsed=0,step=50;
        var cdEl=document.getElementById('loCd'),barEl=document.getElementById('loBar');
        var tmr=setInterval(function(){
            elapsed+=step;
            var pct=Math.max(0,100-(elapsed/total*100));
            var secs=Math.ceil((total-elapsed)/1000);
            if(barEl) barEl.style.width=pct+'%';
            if(cdEl)  cdEl.textContent=secs+'s';
            if(elapsed>=total){clearInterval(tmr);closeL();}
        },step);

        function closeL(){
            clearInterval(tmr);
            ov.style.animation='loOvOut .3s ease forwards';
            card.style.animation='loCardOut .3s ease forwards';
            setTimeout(function(){if(ov.parentNode)ov.parentNode.removeChild(ov);},320);
        }
        btn.addEventListener('click',closeL);
        ov.addEventListener('click',function(e){if(e.target===ov)closeL();});
    })();
    <?php endif; ?>

});
</script>
</body>
</html>
