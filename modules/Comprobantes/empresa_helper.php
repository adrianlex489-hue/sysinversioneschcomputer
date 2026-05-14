<?php
// ============================================================
// empresa_helper.php | Botica 2026
// Función helper para obtener datos de empresa desde cualquier
// comprobante PDF. Incluir con require_once.
// ============================================================

/**
 * Retorna los datos de la empresa desde la BD.
 * Si no existe registro, retorna valores por defecto.
 */
function getEmpresa(PDO $pdo): array {
    try {
        $row = $pdo->query("SELECT * FROM empresa LIMIT 1")->fetch();
        if ($row) return $row;
    } catch (PDOException $e) {}

    // Valores por defecto si la tabla no existe aún
    return [
        'razon_social'    => 'BOTICA SALUD EXPRESS S.A.C.',
        'nombre_comercial'=> 'BOTICA SALUD EXPRESS',
        'ruc'             => '20000000001',
        'direccion'       => 'AV. PRINCIPAL NRO. 123',
        'distrito'        => 'CHICLAYO',
        'provincia'       => 'CHICLAYO',
        'departamento'    => 'LAMBAYEQUE',
        'telefono'        => '',
        'email'           => '',
        'logo'            => '/botica-2026/Logo/SALUD EXPRESS.jpg',
        'igv_porcentaje'  => 18.00,
        'serie_ticket'    => 'T001',
        'serie_nota'      => 'N001',
        'pie_comprobante' => 'Gracias por su preferencia',
    ];
}
