<?php
session_start();
if (isset($_SESSION['id_usuario'])) { header("Location: dashboard.php"); exit; }
require_once '../conf/database.php';
$conexion = $pdo;
$error_message = '';
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username === '' || $password === '') {
        $error_message = "Por favor, ingrese su usuario y su contrasena.";
    } else {
        try {
            $stmt = $conexion->prepare("SELECT id_usuario,username,clave,id_rol,nombre_completo,estado FROM usuarios WHERE username=:u AND estado=1 LIMIT 1");
            $stmt->execute([':u' => $username]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($usuario && password_verify($password, $usuario['clave'])) {
                session_regenerate_id(true);
                $_SESSION['id_usuario']      = $usuario['id_usuario'];
                $_SESSION['username']        = $usuario['username'];
                $_SESSION['id_rol']          = $usuario['id_rol'];
                $_SESSION['nombre_completo'] = $usuario['nombre_completo'];
                header("Location: dashboard.php"); exit;
            } else {
                $error_message = "Credenciales invalidas. Verifique su usuario y contrasena.";
            }
        } catch (PDOException $e) {
            error_log("Error BD login: " . $e->getMessage());
            $error_message = "Error interno del servidor. Intente mas tarde.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Botica 2026 - Acceso al Sistema</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
  --verde:#1a7a4a;--verde-claro:#27ae60;--verde-oscuro:#0d3b26;
  --azul:#1a5276;--rojo:#e74c3c;--blanco:#ffffff;
}
html,body{height:100%;font-family:'Inter',sans-serif;overflow:hidden;}

/* ── WRAPPER ── */
.login-wrapper{display:flex;height:100vh;width:100vw;}

/* ── PANEL IZQUIERDO ── */
.panel-left{
  flex:1;position:relative;overflow:hidden;
  background:linear-gradient(160deg,#0d3b26 0%,#1a7a4a 50%,#27ae60 100%);
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  padding:48px 40px;
}

/* Orbes de fondo */
.orb{
  position:absolute;border-radius:50%;
  background:radial-gradient(circle,rgba(255,255,255,.12),transparent 70%);
  animation:orbPulse ease-in-out infinite;
}
.orb1{width:500px;height:500px;top:-150px;left:-150px;animation-duration:8s;}
.orb2{width:350px;height:350px;bottom:-100px;right:-80px;animation-duration:11s;animation-delay:2s;}
.orb3{width:200px;height:200px;top:40%;left:60%;animation-duration:7s;animation-delay:1s;}
@keyframes orbPulse{0%,100%{transform:scale(1);opacity:.6;}50%{transform:scale(1.15);opacity:1;}}

/* Partículas flotantes */
.particle{
  position:absolute;border-radius:50%;
  background:rgba(255,255,255,.15);
  animation:floatUp linear infinite;
}
@keyframes floatUp{
  0%{transform:translateY(0) rotate(0deg) scale(1);opacity:.7;}
  100%{transform:translateY(-110vh) rotate(720deg) scale(.3);opacity:0;}
}

/* Iconos farmacia flotantes */
.float-icon{
  position:absolute;color:rgba(255,255,255,.1);
  animation:floatIcon ease-in-out infinite;
  font-size:2rem;
}
@keyframes floatIcon{
  0%,100%{transform:translateY(0) rotate(-5deg);}
  50%{transform:translateY(-18px) rotate(5deg);}
}

/* Branding */
.brand-logo{position:relative;z-index:2;text-align:center;margin-bottom:40px;animation:fadeSlideDown .8s ease both;}
@keyframes fadeSlideDown{from{opacity:0;transform:translateY(-30px);}to{opacity:1;transform:translateY(0);}}

/* Logo imagen grande */
.brand-logo .logo-img-wrap{
  position:relative;
  width:140px;height:140px;
  margin:0 auto 20px;
}
.brand-logo .logo-img-wrap .ring{
  position:absolute;inset:-10px;border-radius:50%;
  border:2px solid rgba(255,255,255,.25);
  animation:ringRotate 10s linear infinite;
}
.brand-logo .logo-img-wrap .ring2{
  position:absolute;inset:-20px;border-radius:50%;
  border:1px dashed rgba(255,255,255,.15);
  animation:ringRotate 16s linear infinite reverse;
}
@keyframes ringRotate{to{transform:rotate(360deg);}}
.brand-logo .logo-img-wrap img{
  width:140px;height:140px;
  object-fit:cover;
  border-radius:50%;
  border:4px solid rgba(255,255,255,.35);
  box-shadow:0 12px 40px rgba(0,0,0,.35),0 0 0 8px rgba(255,255,255,.08);
  display:block;
  animation:logoPulse 3s ease-in-out infinite;
}
@keyframes logoPulse{
  0%,100%{box-shadow:0 12px 40px rgba(0,0,0,.35),0 0 0 8px rgba(255,255,255,.08);}
  50%{box-shadow:0 16px 50px rgba(0,0,0,.4),0 0 0 14px rgba(255,255,255,.12);}
}

.brand-logo h1{color:#fff;font-size:2.1rem;font-weight:800;letter-spacing:2px;line-height:1;}
.brand-logo h1 span{color:#a8f0c6;font-weight:300;}
.brand-logo p{color:rgba(255,255,255,.65);font-size:.82rem;margin-top:8px;letter-spacing:.5px;}

/* Features */
.features{position:relative;z-index:2;width:100%;max-width:340px;display:flex;flex-direction:column;gap:12px;}
.feature-item{
  display:flex;align-items:center;gap:14px;
  background:rgba(255,255,255,.08);
  border:1px solid rgba(255,255,255,.12);
  border-radius:12px;padding:13px 16px;
  backdrop-filter:blur(6px);
  transform:translateX(-40px);opacity:0;
  animation:slideInLeft .6s ease forwards;
  transition:transform .25s,background .25s;
}
.feature-item:hover{transform:translateX(5px)!important;background:rgba(255,255,255,.16);}
.feature-item:nth-child(1){animation-delay:.3s;}
.feature-item:nth-child(2){animation-delay:.45s;}
.feature-item:nth-child(3){animation-delay:.6s;}
.feature-item:nth-child(4){animation-delay:.75s;}
@keyframes slideInLeft{to{transform:translateX(0);opacity:1;}}

.fi-icon{
  width:38px;height:38px;border-radius:10px;flex-shrink:0;
  background:rgba(255,255,255,.18);
  display:flex;align-items:center;justify-content:center;
}
.fi-icon i{color:#fff;font-size:.95rem;}
.fi-text strong{display:block;color:#fff;font-size:.85rem;font-weight:600;}
.fi-text span{color:rgba(255,255,255,.6);font-size:.74rem;}

.panel-left-footer{
  position:absolute;bottom:18px;left:0;right:0;
  text-align:center;color:rgba(255,255,255,.35);font-size:.73rem;z-index:2;
}

/* ── PANEL DERECHO ── */
.panel-right{
  width:440px;flex-shrink:0;background:#fff;
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  padding:48px 44px;position:relative;
  box-shadow:-12px 0 50px rgba(0,0,0,.15);
  animation:panelSlideIn .7s cubic-bezier(.22,1,.36,1) both;
}
@keyframes panelSlideIn{from{transform:translateX(60px);opacity:0;}to{transform:translateX(0);opacity:1;}}

/* Línea decorativa superior */
.panel-right::before{
  content:'';position:absolute;top:0;left:0;right:0;height:4px;
  background:linear-gradient(90deg,#1a7a4a,#27ae60,#1a5276);
  background-size:200% 100%;
  animation:gradientSlide 3s ease infinite;
}
@keyframes gradientSlide{0%,100%{background-position:0% 50%;}50%{background-position:100% 50%;}}

/* Header formulario */
.form-header{text-align:center;margin-bottom:32px;width:100%;animation:fadeUp .6s .2s ease both;}
@keyframes fadeUp{from{opacity:0;transform:translateY(20px);}to{opacity:1;transform:translateY(0);}}

.form-header .avatar{
  width:68px;height:68px;margin:0 auto 16px;position:relative;
  background:linear-gradient(135deg,#1a7a4a,#27ae60);
  border-radius:20px;
  display:flex;align-items:center;justify-content:center;
  box-shadow:0 8px 24px rgba(26,122,74,.35);
  animation:avatarBounce 2s 1s ease infinite;
}
@keyframes avatarBounce{0%,100%{transform:translateY(0);}50%{transform:translateY(-5px);}}
.form-header .avatar i{color:#fff;font-size:1.7rem;}
.form-header .avatar .pulse-ring{
  position:absolute;inset:-6px;border-radius:26px;
  border:2px solid rgba(39,174,96,.4);
  animation:pulseRing 2s 1s ease-out infinite;
}
@keyframes pulseRing{0%{transform:scale(1);opacity:.8;}100%{transform:scale(1.25);opacity:0;}}

.form-header h2{font-size:1.45rem;font-weight:700;color:#111827;margin-bottom:4px;}
.form-header p{color:#6b7280;font-size:.86rem;}

/* Campos */
.field-group{width:100%;margin-bottom:16px;animation:fadeUp .5s ease both;}
.field-group:nth-child(1){animation-delay:.35s;}
.field-group:nth-child(2){animation-delay:.45s;}
.field-group:nth-child(3){animation-delay:.55s;}

.field-group label{
  display:block;font-size:.78rem;font-weight:600;
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
  color:#111827;background:#f9fafb;
  transition:border-color .25s,box-shadow .25s,background .25s,transform .15s;
  outline:none;
}
.field-wrap input:focus{
  border-color:#27ae60;background:#fff;
  box-shadow:0 0 0 3px rgba(39,174,96,.15);
  transform:translateY(-1px);
}
.field-wrap:focus-within .field-icon{color:#27ae60;transform:translateY(-50%) scale(1.15);}

.btn-toggle-pass{
  position:absolute;right:12px;top:50%;transform:translateY(-50%);
  background:none;border:none;cursor:pointer;
  color:#9ca3af;font-size:.88rem;padding:4px;
  transition:color .2s,transform .2s;
}
.btn-toggle-pass:hover{color:#27ae60;transform:translateY(-50%) scale(1.2);}

/* Recordarme */
.remember-row{
  display:flex;align-items:center;justify-content:space-between;
  width:100%;margin-bottom:22px;
  animation:fadeUp .5s .6s ease both;
}
.remember-label{
  display:flex;align-items:center;gap:8px;cursor:pointer;
  font-size:.84rem;color:#374151;user-select:none;
}
.remember-label input[type="checkbox"]{
  width:16px;height:16px;accent-color:#27ae60;cursor:pointer;
}

/* Botón submit */
.btn-login{
  width:100%;padding:13px;
  background:linear-gradient(135deg,#1a7a4a 0%,#27ae60 100%);
  color:#fff;border:none;border-radius:10px;
  font-size:.95rem;font-weight:700;font-family:'Inter',sans-serif;
  cursor:pointer;letter-spacing:.3px;
  display:flex;align-items:center;justify-content:center;gap:8px;
  box-shadow:0 4px 15px rgba(26,122,74,.35);
  transition:transform .2s,box-shadow .2s,background .3s;
  position:relative;overflow:hidden;
  animation:fadeUp .5s .65s ease both;
}
/* Efecto shimmer en el botón */
.btn-login::after{
  content:'';position:absolute;
  top:0;left:-100%;width:60%;height:100%;
  background:linear-gradient(90deg,transparent,rgba(255,255,255,.25),transparent);
  transform:skewX(-20deg);
  animation:shimmer 3s 2s ease infinite;
}
@keyframes shimmer{0%{left:-100%;}100%{left:200%;}}
.btn-login:hover{transform:translateY(-2px);box-shadow:0 10px 28px rgba(26,122,74,.45);}
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
  border-radius:10px;padding:12px 16px;margin-bottom:18px;
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
  margin:18px 0 14px;color:#d1d5db;font-size:.74rem;
  animation:fadeUp .5s .7s ease both;
}
.divider::before,.divider::after{content:'';flex:1;height:1px;background:#e5e7eb;}

/* Footer */
.form-footer{
  text-align:center;color:#9ca3af;font-size:.76rem;width:100%;
  animation:fadeUp .5s .75s ease both;
}
.form-footer strong{color:#1a7a4a;}

/* Responsive */
@media(max-width:768px){
  .panel-left{display:none;}
  .panel-right{width:100%;padding:36px 28px;box-shadow:none;}
}
</style>
</head>
<body>
<div class="login-wrapper">

<!-- ══ PANEL IZQUIERDO ══ -->
<div class="panel-left">
  <!-- Orbes -->
  <div class="orb orb1"></div>
  <div class="orb orb2"></div>
  <div class="orb orb3"></div>

  <!-- Partículas -->
  <div class="particle" style="width:10px;height:10px;left:8%;bottom:-10px;animation-duration:16s;animation-delay:0s;"></div>
  <div class="particle" style="width:6px;height:6px;left:20%;bottom:-10px;animation-duration:12s;animation-delay:2s;"></div>
  <div class="particle" style="width:14px;height:14px;left:38%;bottom:-10px;animation-duration:20s;animation-delay:1s;"></div>
  <div class="particle" style="width:8px;height:8px;left:55%;bottom:-10px;animation-duration:14s;animation-delay:4s;"></div>
  <div class="particle" style="width:12px;height:12px;left:72%;bottom:-10px;animation-duration:18s;animation-delay:1.5s;"></div>
  <div class="particle" style="width:5px;height:5px;left:88%;bottom:-10px;animation-duration:11s;animation-delay:3s;"></div>

  <!-- Iconos farmacia flotantes de fondo -->
  <i class="fas fa-capsules float-icon" style="top:12%;left:8%;animation-duration:5s;font-size:2.5rem;"></i>
  <i class="fas fa-heartbeat float-icon" style="top:25%;right:10%;animation-duration:7s;animation-delay:1s;font-size:1.8rem;"></i>
  <i class="fas fa-stethoscope float-icon" style="bottom:30%;left:6%;animation-duration:6s;animation-delay:2s;font-size:2rem;"></i>
  <i class="fas fa-prescription-bottle float-icon" style="bottom:18%;right:8%;animation-duration:8s;animation-delay:.5s;font-size:1.6rem;"></i>
  <i class="fas fa-notes-medical float-icon" style="top:55%;left:15%;animation-duration:9s;animation-delay:3s;font-size:1.4rem;"></i>

  <!-- Branding -->
  <div class="brand-logo">
    <div class="logo-img-wrap">
      <div class="ring"></div>
      <div class="ring2"></div>
      <img src="/botica-2026/public/assets/img/logo.jpg" alt="Salud Express - Botica 2026">
    </div>
    <h1>BOTICA <span>2026</span></h1>
    <p><i class="fas fa-map-marker-alt" style="margin-right:4px;"></i>Sistema de Gestion Farmaceutica</p>
  </div>

  <!-- Features -->
  <div class="features">
    <div class="feature-item">
      <div class="fi-icon"><i class="fas fa-boxes"></i></div>
      <div class="fi-text"><strong>Control de Inventario</strong><span>Gestion de stock y lotes en tiempo real</span></div>
    </div>
    <div class="feature-item">
      <div class="fi-icon"><i class="fas fa-cash-register"></i></div>
      <div class="fi-text"><strong>Ventas y Compras</strong><span>Transacciones al contado y credito</span></div>
    </div>
    <div class="feature-item">
      <div class="fi-icon"><i class="fas fa-calendar-times"></i></div>
      <div class="fi-text"><strong>Control de Vencimientos</strong><span>Alertas automaticas de lotes proximos a vencer</span></div>
    </div>
    <div class="feature-item">
      <div class="fi-icon"><i class="fas fa-chart-line"></i></div>
      <div class="fi-text"><strong>Reportes y Estadisticas</strong><span>Dashboard con metricas clave del negocio</span></div>
    </div>
  </div>

  <div class="panel-left-footer">&copy; <?php echo date('Y'); ?> Botica 2026 &mdash; Todos los derechos reservados</div>
</div>

<!-- ══ PANEL DERECHO ══ -->
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
        <input type="text" id="username" name="username" placeholder="Ingresa tu usuario"
               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
               required autofocus autocomplete="username">
      </div>
    </div>

    <div class="field-group">
      <label for="password"><i class="fas fa-lock" style="margin-right:5px;"></i>Contrasena</label>
      <div class="field-wrap">
        <i class="fas fa-lock field-icon"></i>
        <input type="password" id="password" name="password" placeholder="Ingresa tu contrasena"
               required autocomplete="current-password">
        <button type="button" class="btn-toggle-pass" id="togglePass" title="Mostrar/ocultar">
          <i class="fas fa-eye" id="eyeIcon"></i>
        </button>
      </div>
    </div>

    <div class="remember-row">
      <label class="remember-label">
        <input type="checkbox" id="remember" name="remember">
        Recordar sesion
      </label>
    </div>

    <button type="submit" class="btn-login" id="btnLogin">
      <div class="spinner" id="loginSpinner"></div>
      <i class="fas fa-sign-in-alt" id="loginIcon"></i>
      <span id="loginText">Ingresar al Sistema</span>
    </button>

  </form>

  <div class="divider"><i class="fas fa-shield-alt" style="color:#27ae60;margin-right:4px;"></i>Acceso seguro</div>

  <div class="form-footer">
    <strong>Botica 2026</strong> &mdash; Sistema de Gestion Farmaceutica<br>
    <span style="margin-top:4px;display:block;">
      <i class="fas fa-lock" style="margin-right:3px;"></i>Conexion cifrada
      &nbsp;|&nbsp;
      <i class="fas fa-user-check" style="margin-right:3px;"></i>Sesion verificada
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
  spinner.style.display='block';
  icon.style.display='none';
  text.textContent='Verificando...';
});

// Auto-cerrar alerta
var alertEl = document.getElementById('alertError');
if(alertEl){
  setTimeout(function(){
    alertEl.style.transition='opacity .4s,transform .4s';
    alertEl.style.opacity='0';
    alertEl.style.transform='translateX(20px)';
    setTimeout(function(){ alertEl.remove(); },400);
  },5000);
}

// Efecto ripple en el boton
document.getElementById('btnLogin').addEventListener('mousedown', function(e){
  var btn  = this;
  var rect = btn.getBoundingClientRect();
  var x    = e.clientX - rect.left;
  var y    = e.clientY - rect.top;
  var rip  = document.createElement('span');
  rip.style.cssText = 'position:absolute;border-radius:50%;background:rgba(255,255,255,.35);'+
    'width:0;height:0;left:'+x+'px;top:'+y+'px;transform:translate(-50%,-50%);'+
    'animation:ripple .6s ease-out forwards;pointer-events:none;';
  btn.appendChild(rip);
  setTimeout(function(){ rip.remove(); },600);
});

// Inyectar keyframe ripple
var st = document.createElement('style');
st.textContent='@keyframes ripple{to{width:300px;height:300px;opacity:0;}}';
document.head.appendChild(st);
</script>
</body>
</html>
