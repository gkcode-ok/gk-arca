<?php

namespace GKCode\Arca\Controllers;

use App\Http\Controllers\BaseController;
use App\Models\Generic\Arca\ArcaConnection;
use App\Models\System\Company;
use App\Services\Arca\GkArcaClientService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ArcaConnectionController extends BaseController
{
    protected $modelo = 'Conexion ARCA';
    protected $mainModel = ArcaConnection::class;

    public function __construct(private readonly GkArcaClientService $gkArca) {}

    public function estado(Request $request): array
    {
        $connection = $this->currentConnection($request);

        return [
            'status' => 0,
            'descripcion' => 'Estado de la conexion ARCA',
            'data' => $connection ? $this->presentConnection($connection) : $this->emptyState(),
            'generalConfig' => ArcaConnection::generalConfig(),
        ];
    }

    public function datosComercio(Request $request): array
    {
        $company = Company::query()->find($this->companyId($request));
        $connection = $this->currentConnection($request);

        return [
            'status' => 0,
            'descripcion' => 'Datos del comercio',
            'data' => [
                'cuit' => $connection->cuit ?? '',
                'razon_social' => $connection->razon_social ?? ($company->name ?? ''),
                'condicion_iva' => $connection->condicion_iva ?? '',
                'entorno' => $connection->entorno ?? 'homo',
                'editable' => !$connection || $connection->status === 'not_configured',
            ],
        ];
    }

    public function conectar(Request $request): array
    {
        if (!$this->gkArca->hasAdminConfig()) {
            return ['status' => -1, 'descripcion' => 'GKArca no esta configurado en este servidor. Contacta al administrador del sistema.'];
        }

        $data = Validator::make($request->all(), [
            'cuit' => ['required', 'string', 'size:11'],
            'razon_social' => ['required', 'string', 'max:255'],
            'condicion_iva' => ['required', 'string', 'max:60'],
            'entorno' => ['nullable', 'in:prod,homo'],
        ])->validate();

        $companyId = $this->companyId($request);
        $connection = ArcaConnection::withoutGlobalScopes()->firstOrNew(['company_id' => $companyId]);

        $tenantId = $connection->gkarca_tenant_id;

        if (!$tenantId) {
            $registered = $this->gkArca->registerTenant($data['cuit'], $data['razon_social']);
            if (($registered['ok'] ?? false) !== true) {
                \Illuminate\Support\Facades\Log::warning('GKArca registerTenant fallo', $registered);
                return ['status' => -1, 'descripcion' => 'No fue posible registrar el comercio en ARCA: ' . ($registered['error'] ?? 'error desconocido'), 'error' => $registered['error'] ?? null];
            }

            $payload = (array) ($registered['payload'] ?? []);
            $tenantId = (int) ($payload['tenant']['id'] ?? 0);
            $apiToken = (string) ($payload['api_token'] ?? '');

            if ($tenantId <= 0 || $apiToken === '') {
                return ['status' => -1, 'descripcion' => 'ARCA no devolvio los datos de conexion esperados.'];
            }

            $connection->gkarca_tenant_id = $tenantId;
            $connection->api_token = $apiToken;
        }

        $connection->company_id = $companyId;
        $connection->cuit = $data['cuit'];
        $connection->razon_social = $data['razon_social'];
        $connection->condicion_iva = $data['condicion_iva'];
        $connection->entorno = $data['entorno'] ?? $connection->entorno ?? 'homo';
        $connection->status = 'connected';
        $connection->save();

        return [
            'status' => 0,
            'descripcion' => 'Conexion con ARCA establecida correctamente.',
            'data' => $this->presentConnection($connection),
        ];
    }

    public function certificado(Request $request): array
    {
        $connection = $this->currentConnection($request);
        if (!$connection || !$connection->gkarca_tenant_id) {
            return ['status' => -1, 'descripcion' => 'Primero debes conectar el comercio con ARCA.'];
        }

        $data = Validator::make($request->all(), [
            'cert_file' => ['required', 'file'],
            'key_file' => ['required', 'file'],
            'passphrase' => ['nullable', 'string'],
            'fecha_vencimiento' => ['nullable', 'date'],
        ])->validate();

        // Equivalente a "openssl pkey -in archivo -out archivo.key": normaliza la clave (la desencripta
        // si trae passphrase y la exporta en PEM plano) sin importar el formato/extension original subida.
        try {
            $privateKeyPem = $this->extractPrivateKey(
                file_get_contents($request->file('key_file')->getRealPath()),
                $data['passphrase'] ?? null
            );
        } catch (\RuntimeException $e) {
            return ['status' => -1, 'descripcion' => $e->getMessage()];
        }

        $keyFilename = pathinfo($request->file('key_file')->getClientOriginalName(), PATHINFO_FILENAME) . '.key';

        $uploaded = $this->gkArca->uploadCertificate(
            (int) $connection->gkarca_tenant_id,
            $connection->entorno,
            $connection->cuit,
            $request->file('cert_file'),
            $privateKeyPem,
            $keyFilename,
            $data['passphrase'] ?? null,
            $data['fecha_vencimiento'] ?? null
        );

        if (($uploaded['ok'] ?? false) !== true) {
            return ['status' => -1, 'descripcion' => 'No fue posible cargar el certificado en ARCA.', 'error' => $uploaded['error'] ?? null];
        }

        $payload = (array) ($uploaded['payload'] ?? []);

        $connection->certificate_name = $request->file('cert_file')->getClientOriginalName();
        $connection->certificate_expires_at = $data['fecha_vencimiento'] ?? ($payload['fecha_vencimiento'] ?? null);
        $connection->certificate_status = $this->resolveCertificateStatus($connection->certificate_expires_at);
        $connection->save();

        // La clave se anida dentro de "data" porque el wrapper $gkaxios de multipart (postImagen)
        // solo propaga status/descripcion/data al frontend, descartando claves de primer nivel adicionales.
        $responseData = $this->presentConnection($connection->fresh());
        $responseData['private_key'] = base64_encode($privateKeyPem);
        $responseData['private_key_filename'] = $keyFilename;

        return [
            'status' => 0,
            'descripcion' => 'Certificado digital cargado correctamente.',
            'data' => $responseData,
        ];
    }

    /**
     * Extrae y normaliza una clave privada (PEM/PKCS8, encriptada o no) devolviendo su PEM en texto plano,
     * evitando que el usuario tenga que ejecutar "openssl pkey" manualmente.
     */
    private function extractPrivateKey(string $rawContent, ?string $passphrase): string
    {
        // Los archivos descargados desde Windows pueden traer BOM, CRLF o espacios
        // antes del encabezado PEM. Eso hace fallar algunas versiones de OpenSSL.
        $rawContent = preg_replace('/^\xEF\xBB\xBF/', '', $rawContent) ?? $rawContent;
        $rawContent = trim(str_replace(["\r\n", "\r"], "\n", $rawContent));
        $password = $passphrase !== null ? trim($passphrase) : '';

        $key = openssl_pkey_get_private($rawContent, $password);

        if ($key === false) {
            $opensslError = $this->opensslLastError();
            Log::warning('ARCA: no se pudo leer la clave privada', [
                'openssl_error' => $opensslError,
            ]);
            throw new \RuntimeException('El archivo de clave privada no es válido o la passphrase ingresada es incorrecta. Verificá que sea la clave privada asociada al certificado.');
        }

        // Se exporta sin passphrase porque GK-Arca recibe la clave normalizada.
        if (!openssl_pkey_export($key, $pem, '')) {
            $opensslError = $this->opensslLastError();

            // En algunos entornos con OpenSSL 3 la exportación de una clave RSA
            // PEM válida puede fallar aunque la clave haya sido leída correctamente.
            // Si no está cifrada, el PEM original ya es utilizable por GK-Arca.
            $isEncrypted = str_contains($rawContent, 'BEGIN ENCRYPTED PRIVATE KEY')
                || str_contains($rawContent, 'Proc-Type: 4,ENCRYPTED');
            if (!$isEncrypted && preg_match('/-----BEGIN (?:RSA )?PRIVATE KEY-----/', $rawContent)) {
                Log::notice('ARCA: se utilizará la clave privada PEM original porque OpenSSL no pudo reexportarla', [
                    'openssl_error' => $opensslError,
                ]);
                return rtrim($rawContent) . "\n";
            }

            Log::warning('ARCA: no se pudo exportar la clave privada a PEM', [
                'openssl_error' => $opensslError,
            ]);
            throw new \RuntimeException('No fue posible procesar la clave privada. La clave debe estar en formato PEM (.key o .pem) y ser compatible con OpenSSL.');
        }

        return $pem;
    }

    private function opensslLastError(): string
    {
        $errors = [];
        while (($error = openssl_error_string()) !== false) {
            $errors[] = $error;
        }

        return implode(' | ', $errors);
    }

    public function puntoVenta(Request $request): array
    {
        $connection = $this->currentConnection($request);
        if (!$connection || !$connection->gkarca_tenant_id) {
            return ['status' => -1, 'descripcion' => 'Primero debes conectar el comercio con ARCA.'];
        }

        $data = Validator::make($request->all(), [
            'point_of_sale' => ['required', 'string', 'max:10'],
            'web_service' => ['nullable', 'string', 'max:20'],
        ])->validate();

        $connection->point_of_sale = $data['point_of_sale'];
        $connection->web_service = $data['web_service'] ?? 'WSFEv1';
        $connection->save();

        return [
            'status' => 0,
            'descripcion' => 'Configuracion de facturacion electronica finalizada correctamente.',
            'data' => $this->presentConnection($connection->fresh()),
        ];
    }

    public function probarConexion(Request $request): array
    {
        $connection = $this->currentConnection($request);
        if (!$connection || !$connection->api_token) {
            return ['status' => -1, 'descripcion' => 'No hay una conexion configurada con ARCA.'];
        }

        $result = $this->gkArca->testConnection((string) $connection->api_token, $connection->entorno === 'prod');

        if (($result['ok'] ?? false) !== true) {
            $connection->status = 'error';
            $connection->last_error = $result['error'] ?? 'No fue posible establecer conexion con ARCA.';
            $connection->save();

            return [
                'status' => -1,
                'descripcion' => 'No fue posible establecer conexion con ARCA. Verifica la configuracion del certificado y la autorizacion del Web Service.',
                'data' => $this->presentConnection($connection),
            ];
        }

        $connection->status = 'connected';
        $connection->last_error = null;
        $connection->last_connection_at = now();
        $connection->save();

        return [
            'status' => 0,
            'descripcion' => 'Conexion con ARCA verificada correctamente.',
            'data' => $this->presentConnection($connection),
        ];
    }

    /**
     * Puerta controlada para probar las operaciones expuestas por GKArca desde Admin ARCA.
     */
    public function probarApi(Request $request): array
    {
        $data = Validator::make($request->all(), [
            'operacion' => ['required', 'string'],
            'entorno' => ['nullable'],
            'parametros' => ['nullable', 'array'],
        ])->validate();

        $operations = [
            'setEntorno' => ['method' => 'post', 'path' => '/v1/Arca/setEntorno'],
            'buscarCuit' => ['method' => 'post', 'path' => '/v1/Arca/buscarCuit'],
            'buscarCertificado' => ['method' => 'post', 'path' => '/v1/Arca/buscarCertificado'],
            'comunicaciones' => ['method' => 'post', 'path' => '/v1/Arca/comunicaciones'],
            'constatarComprobante' => ['method' => 'post', 'path' => '/v1/Arca/constatarComprobante'],
            'getUltimoComprobante' => ['method' => 'post', 'path' => '/v1/Arca/Facturacion/getUltimoComprobante'],
            'crearFactura' => ['method' => 'post', 'path' => '/v1/Arca/Facturacion/crearFactura'],
            'consultarFactura' => ['method' => 'post', 'path' => '/v1/Arca/Facturacion/consultarFactura'],
            'getUltimoComprobanteMtxca' => ['method' => 'post', 'path' => '/v1/Arca/FacturacionDetalle/getUltimoComprobante'],
            'crearFacturaMtxca' => ['method' => 'post', 'path' => '/v1/Arca/FacturacionDetalle/crearFactura'],
            'consultarFacturaMtxca' => ['method' => 'post', 'path' => '/v1/Arca/FacturacionDetalle/consultarFactura'],
            'consultarApoc' => ['method' => 'get', 'path' => '/v1/Arca/Apoc/consultar'],
        ];

        abort_unless(isset($operations[$data['operacion']]), 422, 'La operación de ARCA no está habilitada para pruebas.');

        $connection = $this->currentConnection($request);
        if (!$connection || !$connection->api_token) {
            return ['status' => -1, 'descripcion' => 'No hay una conexión configurada con ARCA.'];
        }

        $parameters = $data['parametros'] ?? [];
        if ($data['operacion'] === 'setEntorno') {
            $parameters['entorno'] = filter_var($data['entorno'] ?? false, FILTER_VALIDATE_BOOLEAN);
        }

        $result = $this->gkArca->callTenantApi(
            (string) $connection->api_token,
            filter_var($data['entorno'] ?? ($connection->entorno === 'prod'), FILTER_VALIDATE_BOOLEAN),
            $operations[$data['operacion']]['method'],
            $operations[$data['operacion']]['path'],
            $parameters
        );

        if (($result['ok'] ?? false) !== true) {
            return ['status' => -1, 'descripcion' => $result['error'] ?? 'GKArca devolvió un error.', 'data' => null];
        }

        return [
            'status' => 0,
            'descripcion' => 'Operación ejecutada correctamente.',
            'data' => $result['payload'] ?? null,
        ];
    }

    public function desconectar(Request $request): array
    {
        $connection = $this->currentConnection($request);
        if (!$connection) {
            return ['status' => -1, 'descripcion' => 'No hay una conexion configurada con ARCA.'];
        }

        $connection->status = 'disconnected';
        $connection->save();

        return [
            'status' => 0,
            'descripcion' => 'ARCA fue desconectado correctamente.',
            'data' => $this->presentConnection($connection),
        ];
    }

    private function resolveCertificateStatus($expiresAt): string
    {
        if (!$expiresAt) {
            return 'valid';
        }

        $expiry = Carbon::parse($expiresAt);

        if ($expiry->isPast()) {
            return 'expired';
        }

        if ($expiry->diffInDays(now()) <= 30) {
            return 'expiring';
        }

        return 'valid';
    }

    private function presentConnection(ArcaConnection $connection): array
    {
        $certificateStatus = $connection->certificate_expires_at
            ? $this->resolveCertificateStatus($connection->certificate_expires_at)
            : $connection->certificate_status;

        return [
            'connected' => $connection->status === 'connected',
            'status' => $connection->status,
            'cuit' => $connection->cuit,
            'razon_social' => $connection->razon_social,
            'condicion_iva' => $connection->condicion_iva,
            'entorno' => $connection->entorno,
            'certificate' => [
                'status' => $certificateStatus,
                'name' => $connection->certificate_name,
                'expires_at' => $connection->certificate_expires_at,
            ],
            'web_service' => $connection->web_service,
            'point_of_sale' => $connection->point_of_sale,
            'last_connection' => $connection->last_connection_at,
            'last_error' => $connection->last_error,
        ];
    }

    private function emptyState(): array
    {
        return [
            'connected' => false,
            'status' => 'not_configured',
            'cuit' => null,
            'razon_social' => null,
            'condicion_iva' => null,
            'entorno' => 'homo',
            'certificate' => ['status' => 'none', 'name' => null, 'expires_at' => null],
            'web_service' => null,
            'point_of_sale' => null,
            'last_connection' => null,
            'last_error' => null,
        ];
    }

    private function currentConnection(Request $request): ?ArcaConnection
    {
        return ArcaConnection::query()->where('company_id', $this->companyId($request))->first();
    }

    private function companyId(Request $request): int
    {
        $companyId = (int) optional($request->user())->company_id;
        if ($companyId <= 0 && app()->bound('currentTenant')) {
            $companyId = (int) app('currentTenant')->id;
        }

        abort_if($companyId <= 0, 403, 'Usuario sin empresa asignada.');
        return $companyId;
    }
}
