<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\StockMovement;
use App\Support\Stock;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Validation\Rule;

/**
 * Movimientos de inventario (§7.3): entradas, salidas, ajustes por
 * inventario físico y transferencias entre sucursales, más su historial.
 */
class StockMovementController extends Controller
{
    public const OPERATIONS = [
        'in' => 'Entrada',
        'out' => 'Salida',
        'adjustment' => 'Ajuste (inventario físico)',
        'transfer' => 'Transferencia entre sucursales',
    ];

    public function index()
    {
        $movements = StockMovement::with(['product', 'branch', 'batch', 'user'])
            ->when(request('product_id'), fn ($q, $id) => $q->where('product_id', $id))
            ->when(request('branch_id'), fn ($q, $id) => $q->where('branch_id', $id))
            ->when(request('type'), fn ($q, $type) => $q->where('type', $type))
            ->when(request('reason'), fn ($q, $reason) => $q->where('reason', $reason))
            ->when(request('from'), fn ($q, $from) => $q->where('created_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when(request('to'), fn ($q, $to) => $q->where('created_at', '<=', Carbon::parse($to)->endOfDay()))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.stock-movements.index', [
            'movements' => $movements,
            'branches' => Branch::orderBy('name')->get(),
            'filterProduct' => request('product_id') ? Product::withTrashed()->find(request('product_id')) : null,
        ]);
    }

    public function create()
    {
        $operation = array_key_exists(request('operation'), self::OPERATIONS) ? request('operation') : 'in';

        return view('admin.stock-movements.create', [
            'operation' => $operation,
            'product' => request('product_id') ? Product::find(request('product_id')) : null,
            'branches' => $this->branches(),
        ]);
    }

    public function store()
    {
        $companyId = app(Tenancy::class)->id();
        $branchRule = Rule::exists('branches', 'id')->where('company_id', $companyId)->whereNull('deleted_at');

        $validated = request()->validate([
            'operation' => ['required', Rule::in(array_keys(self::OPERATIONS))],
            'product_id' => ['required', Rule::exists('products', 'id')->where('company_id', $companyId)->whereNull('deleted_at')],
            'branch_id' => ['required', $branchRule],
            'to_branch_id' => ['nullable', 'required_if:operation,transfer', $branchRule],
            'quantity' => 'required|numeric|min:0|max:9999999',
            'reason' => 'nullable|string|max:30',
            'product_batch_id' => ['nullable', Rule::exists('product_batches', 'id')->where('company_id', $companyId)],
            'lot' => 'nullable|string|max:60',
            'expires_at' => 'nullable|date',
            'manufactured_at' => 'nullable|date|before_or_equal:today',
            'unit_cost' => 'nullable|numeric|min:0|max:9999999',
            'notes' => 'nullable|string|max:255',
        ], [
            'to_branch_id.required_if' => 'Elige la sucursal de destino.',
        ], [
            'product_id' => 'producto',
            'branch_id' => 'sucursal',
            'quantity' => 'cantidad',
        ]);

        $product = Product::findOrFail($validated['product_id']);
        $branch = Branch::findOrFail($validated['branch_id']);
        $batch = ! empty($validated['product_batch_id']) ? ProductBatch::findOrFail($validated['product_batch_id']) : null;
        $quantity = (float) $validated['quantity'];
        $notes = $validated['notes'] ?? null;

        // El lote elegido debe ser de ese producto y de esa sucursal.
        if ($batch && ($batch->product_id !== $product->id || $batch->branch_id !== $branch->id)) {
            return back()->withInput()->withErrors(['product_batch_id' => 'El lote no corresponde al producto y la sucursal elegidos.']);
        }

        if ($validated['operation'] !== 'adjustment' && $quantity <= 0) {
            return back()->withInput()->withErrors(['quantity' => 'La cantidad debe ser mayor a cero.']);
        }

        match ($validated['operation']) {
            'in' => Stock::receive($product, $branch, $quantity, $this->reason($validated, StockMovement::MANUAL_IN_REASONS, 'purchase'), [
                'lot' => $validated['lot'] ?? null,
                'expires_at' => $validated['expires_at'] ?? null,
                'manufactured_at' => $validated['manufactured_at'] ?? null,
                'unit_cost' => $validated['unit_cost'] ?? null,
                'notes' => $notes,
            ]),
            'out' => Stock::consume($product, $branch, $quantity, $this->reason($validated, StockMovement::MANUAL_OUT_REASONS, 'internal_use'), [
                'batch' => $batch,
                'notes' => $notes,
            ]),
            'adjustment' => Stock::adjust($product, $branch, $quantity, $batch, $notes),
            'transfer' => Stock::transfer($product, $branch, Branch::findOrFail($validated['to_branch_id']), $quantity, $batch, $notes),
        };

        $redirect = request('return') === 'product'
            ? redirect()->route('products.show', $product)
            : redirect()->route('stock-movements.create', ['operation' => $validated['operation']]);

        return $redirect->with('success', self::OPERATIONS[$validated['operation']] . " registrada: {$product->name}.");
    }

    /** Sucursales activas; si no hay ninguna, se crea la principal. */
    protected function branches()
    {
        $branches = Branch::where('active', true)->orderBy('name')->get();

        return $branches->isNotEmpty() ? $branches : collect([Stock::defaultBranch()]);
    }

    /** @param  array<int, string>  $allowed */
    protected function reason(array $validated, array $allowed, string $default): string
    {
        return in_array($validated['reason'] ?? null, $allowed, true) ? $validated['reason'] : $default;
    }
}
