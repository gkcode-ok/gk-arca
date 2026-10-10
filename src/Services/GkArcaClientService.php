<?php

namespace GKCode\Arca\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GkArcaClientService
{
    private function baseUrl(): string
    {
        return rtrim((string) config('services.gkarca.base_url'), '/');
    }

    private function adminToken(): string
    {
        return (string) config('services.gkarca.admin_token');
    }

    public function hasAdminConfig(): bool
    {
        return $this->baseUrl() !== '' && $this->adminToken() !== '';
    }

    /**
     * Registra (o recupera) el estudio/comercio como Tenant en GKArca.
     * Devuelve el api_token en texto plano SOLO la primera vez.
     */
    public function registerTenant(string $cuit, string $razonSocial): array
    {
        $response = Http::withToken($this->adminToken())
            ->timeout(20)
            ->post($this->baseUrl() . '/v1/saas/tenants', [
                'cuit' => $cuit,
                'razon_social' => $razonSocial,
            ]);

        if (!$response->successful()) {
            $this->logApiError('/v1/saas/tenants', $response->status(), $response->json('message') ?: $response->body());

            return [
                'ok' => false,
                'error' => $response->json('message') ?: $response->body(),
                'status' => $response->status(),
            ];
        }

        return [
            'ok' => true,
            'payload' => $response->json('data'),
        ];
    }

    public function regenerateTenantToken(int $tenantId): array
    {
        $response = Http::withToken($this->adminToken())
            ->timeout(20)
            ->post($this->baseUrl() . "/v1/saas/tenants/{$tenantId}/regenerate-token");

        if (!$response->successful()) {
            $this->logApiError("/v1/saas/tenants/{$tenantId}/regenerate-token", $response->status(), $response->json('message') ?: $response->body());

            return [
                'ok' => false,
                'error' => $response->json('message') ?: $response->body(),
                'status' => $response->status(),
            ];
        }

        return [
            'ok' => true,
            'payload' => $response->json('data'),
        ];
    }

    /**
     * Envia el certificado digital (.crt) y la clave privada (.key, ya normalizada) del comercio a GKArca.
     */
    public function uploadCertificate(
        int $tenantId,
        string $entorno,
        string $cuitFirmante,
        UploadedFile $certFile,
        string $keyContent,
        string $keyFilename,
        ?string $passphrase = null,
        ?string $fechaVencimiento = null
    ): array {
        $response = Http::withToken($this->adminToken())
            ->timeout(30)
            ->attach('cert_file', file_get_contents($certFile->getRealPath()), $certFile->getClientOriginalName())
            ->attach('key_file', $keyContent, $keyFilename)
            ->post($this->baseUrl() . '/v1/saas/certificates', array_filter([
                'tenant_id' => $tenantId,
                'entorno' => $entorno,
                'cuit_firmante' => $cuitFirmante,
                'passphrase' => $passphrase,
                'fecha_vencimiento' => $fechaVencimiento,
            ], fn ($value) => $value !== null));

        if (!$response->successful()) {
            $this->logApiError('/v1/saas/certificates', $response->status(), $response->json('message') ?: $response->body());

            return [
                'ok' => false,
                'error' => $response->json('message') ?: $response->body(),
                'status' => $response->status(),
            ];
        }

        return [
            'ok' => true,
            'payload' => $response->json('data'),
        ];
    }

    /**
     * Consulta el estado del certificado configurado para el tenant en ARCA.
     */
    public function buscarCertificado(string $tenantToken, bool $produccion = false): array
    {
        return $this->tenantRequest($tenantToken, $produccion, 'post', '/v1/Arca/buscarCertificado');
    }

    /**
     * Prueba de conexion real contra ARCA (usada por "Probar conexion").
     */
    public function testConnection(string $tenantToken, bool $produccion = false): array
    {
        return $this->buscarCertificado($tenantToken, $produccion);
    }

    public function buscarCuitMaestro(string $cuit, bool $produccion = false): array
    {
        return $this->adminQuery($produccion, '/v1/Arca/consulta/buscarCuit', ['cuit' => $cuit]);
    }

    public function buscarPorDniMaestro(string $dni, bool $produccion = false): array
    {
        return $this->adminQuery($produccion, '/v1/Arca/consulta/buscarPorDni', ['dni' => $dni]);
    }

    public function buscarImpuestosMaestro(string $cuit, bool $produccion = false): array
    {
        return $this->adminQuery($produccion, '/v1/Arca/consulta/buscarImpuestos', ['cuit' => $cuit]);
    }

    /**
     * Ejecuta una operación de GKArca para las herramientas de diagnóstico del panel.
     * Los paths válidos se controlan en el controlador; el token nunca llega al navegador.
     */
    public function callTenantApi(string $tenantToken, bool $produccion, string $method, string $path, array $payload = []): array
    {
        return $this->tenantRequest($tenantToken, $produccion, strtolower($method), $path, $payload);
    }

    private function tenantRequest(string $tenantToken, bool $produccion, string $method, string $path, array $payload = []): array
    {
        $request = Http::withHeaders([
                'X-Tenant-Token' => $tenantToken,
                'X-Arca-Entorno' => $produccion ? 'true' : 'false',
            ])
            ->timeout(25);

        $response = $method === 'get'
            ? $request->get($this->baseUrl() . $path, $payload)
            : $request->{$method}($this->baseUrl() . $path, $payload);

        if (!$response->successful()) {
            $error = $response->json('descripcion') ?: ($response->json('message') ?: $response->body());
            $this->logApiError($path, $response->status(), $error);

            return [
                'ok' => false,
                'error' => $error,
                'status' => $response->status(),
            ];
        }

        $body = $response->json();

        if (is_array($body) && (int) ($body['status'] ?? 0) !== 0) {
            $error = $body['descripcion'] ?? 'GKArca devolvio un error de negocio.';
            $this->logApiError($path, $response->status(), $error, $body['status']);

            return [
                'ok' => false,
                'error' => $error,
                'status' => 200,
            ];
        }

        return [
            'ok' => true,
            'payload' => is_array($body) ? ($body['data'] ?? $body) : $body,
        ];
    }

    private function adminQuery(bool $produccion, string $path, array $payload): array
    {
        if (!$this->hasAdminConfig()) {
            return ['ok' => false, 'error' => 'GKArca no está configurado para realizar consultas.'];
        }

        $response = Http::withToken($this->adminToken())
            ->withHeaders(['X-Arca-Entorno' => $produccion ? 'true' : 'false'])
            ->timeout(25)
            ->post($this->baseUrl() . $path, $payload);

        if (!$response->successful()) {
            $error = $response->json('descripcion') ?: ($response->json('message') ?: $response->body());
            $this->logApiError($path, $response->status(), $error);

            return ['ok' => false, 'error' => $error, 'status' => $response->status()];
        }

        $body = $response->json();

        if (is_array($body) && (int) ($body['status'] ?? 0) !== 0) {
            $error = $body['descripcion'] ?? 'GKArca devolvió un error de negocio.';
            $this->logApiError($path, $response->status(), $error, $body['status']);

            return ['ok' => false, 'error' => $error, 'status' => $response->status()];
        }

        return [
            'ok' => true,
            'payload' => is_array($body) ? ($body['data'] ?? $body) : $body,
        ];
    }

    private function logApiError(string $path, int $httpStatus, string $error, ?int $apiStatus = null): void
    {
        Log::channel('stack')->error('GKArca respondió con un error.', [
            'service' => 'GKArca',
            'endpoint' => $path,
            'http_status' => $httpStatus,
            'api_status' => $apiStatus,
            'error' => $error,
        ]);
    }
}
