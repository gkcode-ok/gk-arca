<?php

namespace GKCode\Arca\Services;

use App\Models\Generic\Arca\ArcaConnection;
use App\Models\System\PosSale;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PosArcaInvoiceService
{
    public function __construct(private readonly GkArcaClientService $gkArca)
    {
    }

    public function authorize(PosSale $sale, int $receiverConditionId): array
    {
        $connection = ArcaConnection::withoutGlobalScopes()
            ->where('company_id', $sale->company_id)
            ->first();

        if (!$connection || $connection->status !== 'connected' || !$connection->api_token) {
            return $this->fail('ARCA no está conectado para este comercio.');
        }

        $pointOfSale = (int) $connection->point_of_sale;
        if ($pointOfSale <= 0) {
            return $this->fail('Configurá el punto de venta de ARCA antes de facturar.');
        }

        $voucherType = ['factura_a' => 1, 'factura_b' => 6, 'factura_c' => 11][$sale->receipt_type] ?? null;
        if (!$voucherType) {
            return $this->fail('El tipo de comprobante no puede autorizarse en ARCA.');
        }

        try {
            $sale->loadMissing(['items.item', 'customer']);
            $lastVoucher = $this->gkArca->callTenantApi(
                (string) $connection->api_token,
                $connection->entorno === 'prod',
                'post',
                '/v1/Arca/Facturacion/getUltimoComprobante',
                ['PtoVta' => $pointOfSale, 'CbteTipo' => $voucherType],
            );

            if (($lastVoucher['ok'] ?? false) !== true) {
                return $this->fail($lastVoucher['error'] ?? 'ARCA no devolvió el último comprobante autorizado.');
            }

            $lastNumber = $this->lastVoucherNumber((array) ($lastVoucher['payload'] ?? []));
            if ($lastNumber === null) {
                return $this->fail('ARCA devolvió un último comprobante con formato inválido.');
            }

            $documentNumber = $lastNumber + 1;
            $request = $this->buildRequest($sale, $pointOfSale, $voucherType, $documentNumber, $receiverConditionId);
            $authorized = $this->gkArca->callTenantApi(
                (string) $connection->api_token,
                $connection->entorno === 'prod',
                'post',
                '/v1/Arca/Facturacion/crearFactura',
                ['FeCAEReq' => $request],
            );

            if (($authorized['ok'] ?? false) !== true) {
                return $this->fail($authorized['error'] ?? 'ARCA rechazó la solicitud de CAE.');
            }

            $response = (array) ($authorized['payload'] ?? []);
            $detail = data_get($response, 'FeDetResp.FECAEDetResponse', []);
            if (isset($detail[0]) && is_array($detail[0])) {
                $detail = $detail[0];
            }

            if (data_get($response, 'FeCabResp.Resultado') !== 'A' || ($detail['Resultado'] ?? null) !== 'A' || empty($detail['CAE']) || empty($detail['CAEFchVto'])) {
                return $this->fail('ARCA no autorizó el comprobante.', $response);
            }

            $sale->forceFill([
                'status' => 'confirmed',
                'arca_status' => 'authorized',
                'arca_error' => null,
                'arca_point_of_sale' => $pointOfSale,
                'arca_document_number' => (int) ($detail['CbteDesde'] ?? $documentNumber),
                'arca_cae' => $detail['CAE'],
                'arca_cae_expires_at' => Carbon::createFromFormat('Ymd', $detail['CAEFchVto'])->toDateString(),
                'arca_response' => $response,
            ])->save();

            return ['ok' => true, 'sale' => $sale->fresh()];
        } catch (\Throwable $exception) {
            Log::error('POS: error al autorizar comprobante en ARCA.', [
                'pos_sale_id' => $sale->id,
                'message' => $exception->getMessage(),
            ]);

            return $this->fail(
                $exception instanceof \RuntimeException
                    ? $exception->getMessage()
                    : 'No fue posible autorizar el comprobante en ARCA.'
            );
        }
    }

    private function buildRequest(PosSale $sale, int $pointOfSale, int $voucherType, int $documentNumber, int $receiverConditionId): array
    {
        $document = preg_replace('/\D/', '', (string) $sale->customer?->document_number) ?: '';
        $documentType = strlen($document) === 11 ? 80 : (strlen($document) >= 7 ? 96 : 99);
        $documentNumberValue = $documentType === 99 ? 0 : (int) $document;

        if ($voucherType === 1 && $documentType !== 80) {
            throw new \RuntimeException('Factura A requiere un cliente identificado con CUIT.');
        }

        $taxes = $this->taxes($sale);
        $isTypeC = $voucherType === 11;
        $detail = [
            'Concepto' => 1,
            'DocTipo' => $documentType,
            'DocNro' => $documentNumberValue,
            'CbteDesde' => $documentNumber,
            'CbteHasta' => $documentNumber,
            'CbteFch' => now()->format('Ymd'),
            'ImpTotal' => $taxes['total'],
            'ImpTotConc' => 0,
            'ImpNeto' => $isTypeC ? $taxes['total'] : $taxes['net'],
            'ImpOpEx' => 0,
            'ImpIVA' => $isTypeC ? 0 : $taxes['iva'],
            'ImpTrib' => 0,
            'MonId' => 'PES',
            'MonCotiz' => 1,
            'CondicionIVAReceptorId' => $receiverConditionId,
        ];

        if (!$isTypeC && $taxes['iva'] > 0) {
            $detail['Iva'] = ['AlicIva' => $taxes['groups']];
        }

        return [
            'FeCabReq' => ['CantReg' => 1, 'PtoVta' => $pointOfSale, 'CbteTipo' => $voucherType],
            'FeDetReq' => ['FECAEDetRequest' => [$detail]],
        ];
    }

    private function taxes(PosSale $sale): array
    {
        $groups = [];
        $lineTotal = (float) $sale->items->sum(fn ($line) => (float) $line->subtotal);
        if ($lineTotal <= 0) {
            throw new \RuntimeException('La venta no tiene importes facturables.');
        }

        foreach ($sale->items as $line) {
            if (!$line->item) {
                throw new \RuntimeException('No se puede facturar un ítem POS sin artículo de catálogo.');
            }

            $rate = (float) $line->item->default_tax_percentage;
            $rateKey = (string) $rate;
            $groups[$rateKey]['rate'] = $rate;
            $groups[$rateKey]['gross'] = ($groups[$rateKey]['gross'] ?? 0) + round((float) $line->subtotal * (float) $sale->total / $lineTotal, 2);
        }

        $total = round((float) $sale->total, 2);
        $groups = array_values($groups);
        $groups[array_key_last($groups)]['gross'] += $total - round(array_sum(array_column($groups, 'gross')), 2);

        $ivaGroups = [];
        $totalIva = 0;
        foreach ($groups as $index => $group) {
            $rate = $group['rate'];
            $base = round($group['gross'] / (1 + ($rate / 100)), 2);
            $amount = round($group['gross'] - $base, 2);
            $totalIva += $amount;
            $ivaGroups[] = ['Id' => $this->arcaTaxId($rate), 'BaseImp' => $base, 'Importe' => $amount, 'index' => $index];
        }

        $net = round($total - $totalIva, 2);
        $ivaGroups[array_key_last($ivaGroups)]['BaseImp'] += $net - round(array_sum(array_column($ivaGroups, 'BaseImp')), 2);
        foreach ($ivaGroups as &$group) {
            unset($group['index']);
        }

        return ['total' => $total, 'net' => $net, 'iva' => round($totalIva, 2), 'groups' => $ivaGroups];
    }

    private function arcaTaxId(float $rate): int
    {
        return match (round($rate, 2)) {
            0.0 => 3,
            2.5 => 9,
            5.0 => 8,
            10.5 => 4,
            21.0 => 5,
            27.0 => 6,
            default => throw new \RuntimeException("La alícuota de IVA {$rate}% no está habilitada para ARCA."),
        };
    }

    private function lastVoucherNumber(array $payload): ?int
    {
        foreach (['CbteNro', 'cbte_nro', 'numero', 'number'] as $key) {
            if (isset($payload[$key]) && is_numeric($payload[$key])) {
                return (int) $payload[$key];
            }
        }

        return null;
    }

    private function fail(string $error, ?array $response = null): array
    {
        return ['ok' => false, 'error' => $error, 'response' => $response];
    }
}