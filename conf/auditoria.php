<?php
// ============================================================
// conf/auditoria.php | SysInversiones CH Computer
// Helper centralizado para registrar eventos de auditoría.
// Incluir en los módulos que requieran trazabilidad.
// ============================================================

/**
 * Registra un evento de auditoría en la tabla `auditoria`.
 *
 * @param PDO    $pdo         Conexión activa a la BD
 * @param string $modulo      Módulo del sistema: 'productos', 'ventas', 'caja', etc.
 * @param string $accion      Acción realizada: 'crear', 'editar', 'eliminar', 'ajuste', 'anular', etc.
 * @param string $tabla       Tabla de BD afectada: 'productos', 'ventas', etc.
 * @param int    $id_registro ID del registro afectado (0 si no aplica)
 * @param string $descripcion Descripción legible del evento
 * @param string $campo       Campo específico modificado (vacío si no aplica)
 * @param mixed  $valor_antes Valor anterior (null si es creación)
 * @param mixed  $valor_nuevo Valor nuevo (null si es eliminación)
 */
function registrarAuditoria(
    PDO    $pdo,
    string $modulo,
    string $accion,
    string $tabla,
    int    $id_registro,
    string $descripcion,
    string $campo       = '',
    mixed  $valor_antes = null,
    mixed  $valor_nuevo = null
): void {
    try {
        $id_usuario = (int)($_SESSION['id_usuario'] ?? 0);
        $ip         = obtenerIP();

        // Convertir arrays/objetos a JSON para almacenamiento
        if (is_array($valor_antes) || is_object($valor_antes)) {
            $valor_antes = json_encode($valor_antes, JSON_UNESCAPED_UNICODE);
        }
        if (is_array($valor_nuevo) || is_object($valor_nuevo)) {
            $valor_nuevo = json_encode($valor_nuevo, JSON_UNESCAPED_UNICODE);
        }

        // Truncar valores muy largos
        $valor_antes = $valor_antes !== null ? mb_substr((string)$valor_antes, 0, 65535) : null;
        $valor_nuevo = $valor_nuevo !== null ? mb_substr((string)$valor_nuevo, 0, 65535) : null;
        $descripcion = mb_substr($descripcion, 0, 255);
        $campo       = mb_substr($campo, 0, 80);

        $pdo->prepare("
            INSERT INTO auditoria
                (id_usuario, modulo, accion, tabla, id_registro, campo, valor_antes, valor_nuevo, descripcion, ip, fecha)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ")->execute([
            $id_usuario,
            $modulo,
            $accion,
            $tabla,
            $id_registro ?: null,
            $campo       ?: null,
            $valor_antes,
            $valor_nuevo,
            $descripcion ?: null,
            $ip,
        ]);

    } catch (PDOException $e) {
        // La auditoría nunca debe interrumpir la operación principal
        // Solo registrar en error_log si está disponible
        error_log('[AUDITORIA ERROR] ' . $e->getMessage());
    }
}

/**
 * Registra múltiples cambios de campos en una sola operación de edición.
 * Compara dos arrays (antes/después) y registra solo los campos que cambiaron.
 *
 * @param PDO    $pdo         Conexión activa
 * @param string $modulo      Módulo del sistema
 * @param string $tabla       Tabla afectada
 * @param int    $id_registro ID del registro
 * @param array  $antes       Array con valores anteriores ['campo' => valor]
 * @param array  $despues     Array con valores nuevos ['campo' => valor]
 * @param array  $campos_ignorar Campos a no auditar (ej: timestamps)
 */
function registrarCambios(
    PDO    $pdo,
    string $modulo,
    string $tabla,
    int    $id_registro,
    array  $antes,
    array  $despues,
    array  $campos_ignorar = ['fecha_registro', 'updated_at', 'created_at']
): void {
    $cambios = [];

    foreach ($despues as $campo => $valor_nuevo) {
        if (in_array($campo, $campos_ignorar)) continue;
        $valor_antes = $antes[$campo] ?? null;

        // Comparar con conversión de tipo para evitar falsos positivos
        if ((string)$valor_antes !== (string)$valor_nuevo) {
            $cambios[$campo] = [
                'antes'  => $valor_antes,
                'nuevo'  => $valor_nuevo,
            ];
        }
    }

    if (empty($cambios)) return;

    // Si hay pocos cambios, registrar uno por campo
    if (count($cambios) <= 3) {
        foreach ($cambios as $campo => $vals) {
            $desc = "Cambio en $tabla #$id_registro — campo '$campo'";
            registrarAuditoria($pdo, $modulo, 'editar', $tabla, $id_registro, $desc, $campo, $vals['antes'], $vals['nuevo']);
        }
    } else {
        // Muchos cambios: registrar como JSON compacto
        $antes_json  = array_map(fn($c) => $c['antes'], $cambios);
        $nuevos_json = array_map(fn($c) => $c['nuevo'], $cambios);
        $campos_str  = implode(', ', array_keys($cambios));
        $desc = "Edición de $tabla #$id_registro — campos: $campos_str";
        registrarAuditoria($pdo, $modulo, 'editar', $tabla, $id_registro, $desc, '', $antes_json, $nuevos_json);
    }
}

/**
 * Obtiene la IP real del cliente, considerando proxies.
 */
function obtenerIP(): string {
    $headers = [
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR',
    ];
    foreach ($headers as $h) {
        if (!empty($_SERVER[$h])) {
            $ip = trim(explode(',', $_SERVER[$h])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return '0.0.0.0';
}
