<?php

namespace App\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Models\Generic\Person;
use App\Models\System\PosSale;
use App\Models\System\Publication;
use App\Models\System\Item;
use App\Models\System\Stock;
use App\Models\System\StockMovement;
use App\Services\Arca\PosArcaInvoiceService;
use App\Services\Report\ReportManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosController extends BaseController
{
    public function __construct(private readonly PosArcaInvoiceService $posArca)
    {
    }

    public function products(Request $request): array
    {
        $term = trim((string) $request->input('q', ''));
        $companyId = (int) $request->user()->company_id;
        $branchId = $request->user()->branch_id;
        $items = Item::query()->with(['product', 'stocks' => fn ($query) => $query->when($branchId, fn ($stock) => $stock->where('branch_id', $branchId))])
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereHas('product', fn ($query) => $query->where('is_active', true))
            ->when($term !== '', fn ($query) => $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('barcode', $term)
                    ->orWhere('qr_code', $term)
                    ->orWhere('sku', $term)
                    ->orWhereHas('product', fn ($product) => $product->where('name', 'like', "%{$term}%"));
            }))
            ->limit(12)->get();

        $products = $items->map(function (Item $item) {
            $stock = $item->stocks->sum(fn (Stock $stock) => (float) $stock->quantity - (float) $stock->reserved_quantity);
            return [
                'id' => $item->id,
                'item_id' => $item->id,
                'identifier' => $item->product->slug,
                'barcode' => $item->barcode,
                'sku' => $item->sku,
                'title' => $item->name ?: $item->product->name,
                'price' => $item->calculated_price,
                'sale_unit' => 'unit',
                'stock' => $stock,
                'allow_negative_stock' => false,
            ];
        })->values();

        return ['status' => 0, 'data' => $products];
    }

    public function customers(Request $request): array
    {
        $term = trim((string) $request->input('q', ''));
        $customers = Person::query()->where('company_id', $request->user()->company_id)->clients()
            ->when($term !== '', fn ($query) => $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")->orWhere('last_name', 'like', "%{$term}%")->orWhere('document_number', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%");
            }))->limit(12)->get(['id', 'name', 'last_name', 'document_number', 'phone']);
        return ['status' => 0, 'data' => $customers];
    }

    public function store(Request $request): array
    {
        $data = $request->validate([
            'branch_id' => ['nullable', 'integer'], 'customer_person_id' => ['nullable', 'integer'],
            'receipt_type' => ['required', 'in:ticket,factura_a,factura_b,factura_c'],
            'customer_person_id' => ['required_if:receipt_type,factura_a', 'nullable', 'integer'],
            'fiscal_condition_id' => ['nullable', 'integer', 'in:1,4,5,6'],
            'discount' => ['nullable', 'numeric', 'min:0'], 'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['nullable', 'integer', 'required_without:items.*.publication_id'],
            'items.*.publication_id' => ['nullable', 'integer', 'required_without:items.*.item_id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'], 'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', 'string', 'max:32'], 'payments.*.amount' => ['required', 'numeric', 'gt:0'],
        ]);
        $companyId = (int) $request->user()->company_id;
        $sale = DB::transaction(function () use ($data, $companyId, $request) {
            $items = collect($data['items'])->groupBy(fn ($row) => ($row['item_id'] ?? 'publication:' . $row['publication_id']))->map(fn ($rows) => [
                'quantity' => $rows->sum('quantity'), 'discount' => $rows->sum(fn ($row) => (float) ($row['discount'] ?? 0)),
            ]);
            $lines = []; $subtotal = 0; $discount = (float) ($data['discount'] ?? 0);
            foreach ($items as $key => $line) {
                $isCatalogItem = !str_starts_with((string) $key, 'publication:');
                $id = $isCatalogItem ? (int) $key : (int) str_replace('publication:', '', $key);
                $product = $isCatalogItem
                    ? Item::with(['product', 'stocks' => fn ($query) => $query->where('branch_id', $data['branch_id'] ?? $request->user()->branch_id)->lockForUpdate()])->where('company_id', $companyId)->lockForUpdate()->find($id)
                    : Publication::where('company_id', $companyId)->lockForUpdate()->find($id);
                if (!$product) throw ValidationException::withMessages(['items' => 'Uno de los productos no pertenece a este comercio.']);
                $qty = (float) $line['quantity'];
                $available = $isCatalogItem ? $product->stocks->sum(fn (Stock $stock) => (float) $stock->quantity - (float) $stock->reserved_quantity) : (float) $product->stock;
                if ($isCatalogItem && !$product->is_stockable) $available = $qty;
                if ($available < $qty) {
                    $productName = $isCatalogItem ? ($product->name ?: $product->product->name) : $product->title;
                    throw ValidationException::withMessages(['items' => "{$productName} no tiene stock suficiente."]);
                }
                $unitPrice = $isCatalogItem ? $product->calculated_price : (float) $product->price;
                $lineGross = round($unitPrice * $qty, 2);
                $lineDiscount = min((float) $line['discount'], $lineGross);
                $lineSubtotal = round($lineGross - $lineDiscount, 2); $subtotal += $lineGross; $discount += $lineDiscount;
                $lines[] = compact('product', 'qty', 'lineDiscount', 'lineSubtotal', 'isCatalogItem');
            }
            $discount = min($discount, $subtotal);
            $total = round($subtotal - $discount, 2);
            $paid = round(collect($data['payments'])->sum('amount'), 2);
            if ($paid < $total) throw ValidationException::withMessages(['payments' => 'El pago total es inferior al total de la venta.']);
            $sale = PosSale::create(['company_id' => $companyId, 'branch_id' => $data['branch_id'] ?? $request->user()->branch_id, 'customer_person_id' => $data['customer_person_id'] ?? null, 'created_by' => $request->user()->id, 'receipt_type' => $data['receipt_type'], 'subtotal' => $subtotal, 'discount' => $discount, 'total' => $total, 'paid' => $paid, 'change_amount' => max(0, $paid - $total), 'status' => 'pending_authorization', 'arca_status' => 'not_requested']);
            foreach ($lines as $line) {
                $product = $line['product'];
                $sale->items()->create(['publication_id' => $line['isCatalogItem'] ? null : $product->id, 'item_id' => $line['isCatalogItem'] ? $product->id : null, 'quantity' => $line['qty'], 'sale_unit' => $line['isCatalogItem'] ? 'unit' : ($product->sale_unit ?: 'unit'), 'unit_price' => $line['isCatalogItem'] ? $product->calculated_price : $product->price, 'discount' => $line['lineDiscount'], 'subtotal' => $line['lineSubtotal']]);
                if ($line['isCatalogItem'] && $product->is_stockable) {
                    $stock = $product->stocks->first();
                    $stock->decrement('quantity', $line['qty']);
                    StockMovement::create(['company_id' => $companyId, 'item_id' => $product->id, 'branch_id' => $stock->branch_id, 'type' => 'sale', 'direction' => 'out', 'quantity' => $line['qty'], 'reason' => 'Venta POS', 'reference_type' => PosSale::class, 'reference_id' => $sale->id, 'user_id' => $request->user()->id]);
                } elseif (!$line['isCatalogItem'] && !$product->allow_negative_stock) {
                    $product->decrement('stock', $line['qty']);
                }
            }
            $sale->payments()->createMany($data['payments']);
            return $sale->load(['items.product', 'items.item.product', 'payments']);
        });
        $isFiscal = $sale->receipt_type !== 'ticket';
        if ($isFiscal) {
            $authorization = $this->posArca->authorize($sale, (int) ($data['fiscal_condition_id'] ?? 5));
            if (($authorization['ok'] ?? false) !== true) {
                $sale->forceFill([
                    'arca_status' => 'rejected',
                    'arca_error' => $authorization['error'],
                    'arca_response' => $authorization['response'] ?? null,
                ])->save();

                return ['status' => -1, 'descripcion' => $authorization['error'], 'data' => $sale->fresh()];
            }
            $sale = $authorization['sale'];
        } else {
            $sale->forceFill(['status' => 'confirmed', 'arca_status' => 'not_required'])->save();
        }

        return [
            'status' => 0,
            'descripcion' => $isFiscal
                ? 'Factura autorizada por ARCA. Comprobante disponible para imprimir.'
                : 'Venta registrada. Ticket disponible para imprimir.',
            'data' => [
                'sale' => $sale,
                'receipt_url' => route('Arca.pos.ventas.comprobante', ['sale' => $sale->id]),
            ],
        ];
    }

    public function receipt(Request $request, int $sale)
    {
        $sale = PosSale::query()
            ->with(['items.product', 'items.item.product', 'payments', 'customer'])
            ->where('company_id', $request->user()->company_id)
            ->findOrFail($sale);

        $reportManager = app(ReportManager::class);

        return $reportManager->renderPdf(
            'reports.arca.pos_ticket',
            [
                'sale' => $sale,
                'tenant' => $reportManager->resolveTenantBranding($sale->company_id),
            ],
            "Ticket_POS_{$sale->id}",
            true,
            ['paper' => [0, 0, 226.77, 841.89]],
        );
    }
}
