<?php
// ============================================================
// empresa_helper.php | SysInversiones CH Computer 2026
// Helper para obtener datos de empresa desde la BD.
// Incluir con require_once en cualquier comprobante PDF.
// ============================================================

function getEmpresa(PDO $pdo): array {
    try {
        $row = $pdo->query("SELECT * FROM empresa LIMIT 1")->fetch();
        if ($row) return $row;
    } catch (PDOException $e) {}

    // Valores por defecto (se usan si la tabla aún no tiene datos)
    return [
        'razon_social'    => 'INVERSIONES CH COMPUTER SRL',
        'nombre_comercial'=> 'SYSINVERSIONES CH COMPUTER',
        'ruc'             => '20479894699',
        'direccion'       => 'CAL. JOSE FRANCISCO CABRERA NRO. 274',
        'distrito'        => 'CHICLAYO',
        'provincia'       => 'CHICLAYO',
        'departamento'    => 'LAMBAYEQUE',
        'telefono'        => '939683782',
        'email'           => 'inversiones123@gmail.com',
        'web'             => '',
        'logo'            => '/sysinversioneschcomputer/Logo/logo.jpg',
        'igv_porcentaje'  => 18.00,
        'serie_ticket'    => 'T001',
        'serie_nota'      => 'N001',
        'pie_comprobante' => 'Gracias por su preferencia',
    ];
}
