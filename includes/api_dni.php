<?php
/**
 * api_dni.php — Consulta de DNI via miapi.cloud
 * Adaptado para el sistema SysInversiones CH Computer
 */
class APIDni {

    private $api_url   = 'https://miapi.cloud/v1/dni/';
    private $api_token = null;
    private $timeout   = 10;

    public function __construct($token) {
        $this->api_token = $token;
    }

    /**
     * Consulta un DNI en la API externa.
     * @param  string $dni  8 dígitos
     * @return array  ['success'=>bool, 'error'=>string|null, 'datos'=>array|null]
     */
    public function consultar(string $dni): array {
        $dni = trim(preg_replace('/[^0-9]/', '', $dni));

        if (strlen($dni) !== 8) {
            return ['success' => false, 'error' => 'El DNI debe contener exactamente 8 dígitos.', 'datos' => null];
        }

        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $this->api_url . $dni,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => $this->timeout,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $this->api_token,
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);

            $response   = curl_exec($ch);
            $http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);

            if ($curl_error) {
                return ['success' => false, 'error' => 'Error de conexión: ' . $curl_error, 'datos' => null];
            }

            if ($http_code !== 200) {
                return ['success' => false, 'error' => "La API respondió con código HTTP $http_code.", 'datos' => null];
            }

            $data = json_decode($response, true);

            if (!is_array($data) || !isset($data['success'])) {
                return ['success' => false, 'error' => 'Respuesta inválida de la API.', 'datos' => null];
            }

            return $data;

        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Excepción: ' . $e->getMessage(), 'datos' => null];
        }
    }

    /**
     * Formatea la respuesta de la API al esquema de la tabla `clientes` de bdinversioneschcomputer.
     * Campos: nombres, apellido_paterno, apellido_materno, dni, direccion, tipo_documento
     *
     * @param  array $api_response  Respuesta cruda de consultar()
     * @return array|null
     */
    public static function formatear_datos(array $api_response): ?array {
        if (empty($api_response['success']) || empty($api_response['datos'])) {
            return null;
        }

        $d = $api_response['datos'];

        // Construir dirección completa desde los campos de domicilio
        $partes_dir = array_filter([
            $d['domiciliado']['direccion']   ?? '',
            $d['domiciliado']['distrito']    ?? '',
            $d['domiciliado']['provincia']   ?? '',
            $d['domiciliado']['departamento'] ?? '',
        ]);

        return [
            'nombres'          => strtoupper(trim($d['nombres']      ?? '')),
            'apellido_paterno' => strtoupper(trim($d['ape_paterno']  ?? '')),
            'apellido_materno' => strtoupper(trim($d['ape_materno']  ?? '')),
            'dni'              => $d['dni'] ?? '',
            'direccion'        => implode(', ', $partes_dir),
            'tipo_documento'   => 'DNI',
        ];
    }
}
