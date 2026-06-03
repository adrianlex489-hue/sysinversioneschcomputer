<?php
// ============================================================
// modules/servicios/ajax_fotos_taller.php
// Endpoint AJAX para subir y eliminar fotos del taller
// ============================================================
$ruta_base = '../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'conf/verificar_acceso.php';

header('Content-Type: application/json');

if (!isset($pdo) || !($pdo instanceof PDO)) {
    echo json_encode(['success' => false, 'error' => 'Sin conexión BD']);
    exit;
}

$accion   = $_POST['accion'] ?? $_GET['accion'] ?? '';
$id_orden = (int)($_POST['id_orden'] ?? $_GET['id_orden'] ?? 0);

if (!$id_orden) {
    echo json_encode(['success' => false, 'error' => 'ID de orden inválido']);
    exit;
}

// ── Directorio de uploads ─────────────────────────────────────────────────────
$upload_dir = $ruta_base . 'uploads/taller/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// ── Helper: leer fotos actuales de la orden ───────────────────────────────────
function getFotosTaller(PDO $pdo, int $id_orden): array {
    $stmt = $pdo->prepare("SELECT fotos_taller FROM ordenes_servicio WHERE id_orden = ?");
    $stmt->execute([$id_orden]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || empty($row['fotos_taller'])) return [];
    $decoded = json_decode($row['fotos_taller'], true);
    return is_array($decoded) ? $decoded : [];
}

// ── Helper: guardar fotos en BD ───────────────────────────────────────────────
function saveFotosTaller(PDO $pdo, int $id_orden, array $fotos): void {
    $pdo->prepare("UPDATE ordenes_servicio SET fotos_taller = ? WHERE id_orden = ?")
        ->execute([json_encode(array_values($fotos)), $id_orden]);
}

// ════════════════════════════════════════════════════════════
// ACCIÓN: subir foto
// ════════════════════════════════════════════════════════════
if ($accion === 'subir') {
    if (empty($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => 'No se recibió ningún archivo o hubo un error de subida.']);
        exit;
    }

    $file     = $_FILES['foto'];
    $maxSize  = 5 * 1024 * 1024; // 5 MB
    $allowed  = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    // Validar tamaño
    if ($file['size'] > $maxSize) {
        echo json_encode(['success' => false, 'error' => 'La imagen supera el límite de 5 MB.']);
        exit;
    }

    // Validar tipo MIME real (no confiar en extensión)
    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowed)) {
        echo json_encode(['success' => false, 'error' => 'Tipo de archivo no permitido. Solo JPG, PNG, WEBP o GIF.']);
        exit;
    }

    // Verificar límite de fotos por orden (máx 10)
    $fotosActuales = getFotosTaller($pdo, $id_orden);
    if (count($fotosActuales) >= 10) {
        echo json_encode(['success' => false, 'error' => 'Límite de 10 fotos por orden alcanzado.']);
        exit;
    }

    // Generar nombre único
    $ext      = match($mimeType) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
        default      => 'jpg'
    };
    $filename = 'taller_' . $id_orden . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $destPath = $upload_dir . $filename;
    $webPath  = 'uploads/taller/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        echo json_encode(['success' => false, 'error' => 'Error al guardar el archivo en el servidor.']);
        exit;
    }

    // Guardar en BD
    $fotosActuales[] = $webPath;
    saveFotosTaller($pdo, $id_orden, $fotosActuales);

    echo json_encode([
        'success'  => true,
        'ruta'     => $webPath,
        'total'    => count($fotosActuales),
        'message'  => 'Foto subida correctamente.'
    ]);
    exit;
}

// ════════════════════════════════════════════════════════════
// ACCIÓN: eliminar foto
// ════════════════════════════════════════════════════════════
if ($accion === 'eliminar') {
    $ruta = trim($_POST['ruta'] ?? '');
    if (empty($ruta)) {
        echo json_encode(['success' => false, 'error' => 'Ruta no especificada.']);
        exit;
    }

    // Seguridad: solo permitir rutas dentro de uploads/taller/
    if (!preg_match('#^uploads/taller/taller_\d+_[a-f0-9]+\.(jpg|png|webp|gif)$#i', $ruta)) {
        echo json_encode(['success' => false, 'error' => 'Ruta no válida.']);
        exit;
    }

    $fotos = getFotosTaller($pdo, $id_orden);
    $fotos = array_filter($fotos, fn($f) => $f !== $ruta);

    // Eliminar archivo físico
    $filePath = $ruta_base . $ruta;
    if (file_exists($filePath)) {
        @unlink($filePath);
    }

    saveFotosTaller($pdo, $id_orden, array_values($fotos));

    echo json_encode([
        'success' => true,
        'total'   => count($fotos),
        'message' => 'Foto eliminada.'
    ]);
    exit;
}

// ════════════════════════════════════════════════════════════
// ACCIÓN: listar fotos
// ════════════════════════════════════════════════════════════
if ($accion === 'listar') {
    $fotos = getFotosTaller($pdo, $id_orden);
    echo json_encode(['success' => true, 'fotos' => $fotos, 'total' => count($fotos)]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Acción no reconocida.']);
