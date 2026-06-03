<?php
/**
 * api_ruc.php — Consulta de RUC via miapi.cloud
 * Adaptado para el sistema SysInversiones CH Computer
 */
class APIRuc {

    private $api_url   = 'https://miapi.cloud/v1/ruc/';
    private $api_token = null;
    private $timeout   = 10;

    public function __construct($token) {
        $this->api_token = $token;
    }

    /**
     * Consulta un RUC en la API externa.
     * @param  string $ruc  11 dígitos
     * @return array  ['success'=>bool, 'error'=>string|null, 'datos'=>array|null]
     */
    public function consultar(string $ruc): array {
        $ruc = trim(preg_replace('/[^0-9]/', '', $ruc));

        if (strlen($ruc) !== 11) {
            return ['success' => false, 'error' => 'El RUC debe contener exactamente 11 dígitos.', 'datos' => null];
        }

        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $this->api_url . $ruc,
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
     * Formatea la respuesta de la API al esquema de la tabla `proveedores` de bdinversioneschcomputer.
     * Campos: razon_social, ruc, direccion
     *
     * @param  array $api_response  Respuesta cruda de consultar()
     * @return array|null
     */
    public static function formatear_datos(array $api_response): ?array {
        if (empty($api_response['success']) || empty($api_response['datos'])) {
            return null;
        }

        $d = $api_response['datos'];

        $partes_dir = array_filter([
            $d['domiciliado']['direccion']    ?? '',
            $d['domiciliado']['distrito']     ?? '',
            $d['domiciliado']['provincia']    ?? '',
            $d['domiciliado']['departamento'] ?? '',
        ]);

        return [
            'razon_social' => strtoupper(trim($d['razon_social'] ?? '')),
            'ruc'          => $d['ruc'] ?? '',
            'direccion'    => implode(', ', $partes_dir),
            'estado_sunat' => $d['estado']    ?? '',
            'condicion'    => $d['condicion'] ?? '',
        ];
    }
}
