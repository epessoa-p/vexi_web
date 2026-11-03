<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductCategory;
use App\Support\Stock;
use App\Support\Tenancy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** Productos y medicamentos (§7.1), con su existencia, lotes y kardex. */
class ProductController extends Controller
{
    public function index()
    {
        $branchId = request('branch_id') ? (int) request('branch_id') : null;
        $expiringUntil = now()->addDays(config('vexi.expiry_alert_days'))->endOfDay();

        $products = Product::with(['category', 'stocks'])
            ->search(request('q'))
            ->when(request('category_id'), fn ($q, $id) => $q->where('product_category_id', $id))
            ->when(request('status') === 'inactive', fn ($q) => $q->where('active', false), fn ($q) => $q->where('active', true))
            ->when(request('status') === 'low', fn ($q) => $q->lowStock())
            ->when(request('status') === 'out', fn ($q) => $q->whereRaw('(select coalesce(sum(quantity), 0) from product_stocks where product_stocks.product_id = products.id) <= 0'))
            ->when(request('status') === 'expiring', fn ($q) => $q->whereHas('batches', fn ($b) => $b->available()->where('expires_at', '<=', $expiringUntil)))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.products.index', [
            'products' => $products,
            'categories' => ProductCategory::orderBy('name')->get(),
            'branches' => Branch::where('active', true)->orderBy('name')->get(),
            'branchId' => $branchId,
            'limit' => $this->planLimitStatus(app(Tenancy::class)->id(), 'products'),
        ]);
    }

    public function create()
    {
        return view('admin.products.create', $this->formData() + ['product' => null]);
    }

    public function store()
    {
        if ($this->planLimitReached(app(Tenancy::class)->id(), 'products')) {
            return back()->withInput()->withErrors([
                'error' => 'Alcanzaste el límite de productos de tu plan. Contacta a tu proveedor para ampliarlo.',
            ]);
        }

        $validated = $this->validateProduct();
        $initial = request()->validate([
            'initial_quantity' => 'nullable|numeric|min:0.01|max:9999999',
            'initial_branch_id' => ['nullable', Rule::exists('branches', 'id')->where('company_id', app(Tenancy::class)->id())],
            'initial_lot' => 'nullable|string|max:60',
            'initial_expires_at' => 'nullable|date',
        ]);

        $product = DB::transaction(function () use ($validated, $initial) {
            $product = Product::create(collect($validated)->except('image')->all() + [
                'track_batches' => request()->boolean('track_batches'),
                'active' => request()->boolean('active', true),
                'image' => $this->storeImage(request()->file('image')),
            ]);

            // Existencia inicial: queda en el kardex como "Stock inicial".
            if (! empty($initial['initial_quantity'])) {
                $branch = ! empty($initial['initial_branch_id']) ? Branch::find($initial['initial_branch_id']) : Stock::defaultBranch();
                Stock::receive($product, $branch, (float) $initial['initial_quantity'], 'initial', [
                    'lot' => $initial['initial_lot'] ?? null,
                    'expires_at' => $initial['initial_expires_at'] ?? null,
                    'unit_cost' => $product->purchase_price,
                ]);
            }

            return $product;
        });

        return redirect()->route('products.show', $product)->with('success', 'Producto creado.');
    }

    public function show(Product $product)
    {
        $product->load(['category', 'stocks.branch']);

        return view('admin.products.show', [
            'product' => $product,
            'batches' => $product->batches()->with('branch')->available()
                ->orderByRaw('expires_at is null')->orderBy('expires_at')->get(),
            'movements' => $product->movements()->with(['branch', 'batch', 'user'])
                ->when(request('branch_id'), fn ($q, $id) => $q->where('branch_id', $id))
                ->latest('id')->paginate(25)->withQueryString(),
            'branches' => Branch::where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function edit(Product $product)
    {
        return view('admin.products.edit', $this->formData() + ['product' => $product]);
    }

    public function update(Product $product)
    {
        $validated = $this->validateProduct($product);

        $data = collect($validated)->except('image')->all() + [
            'track_batches' => request()->boolean('track_batches'),
            'active' => request()->boolean('active'),
        ];

        if (request()->boolean('remove_image') || request()->hasFile('image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $data['image'] = $this->storeImage(request()->file('image'));
        }

        $product->update($data);

        return redirect()->route('products.show', $product)->with('success', 'Producto actualizado.');
    }

    public function destroy(Product $product)
    {
        // Soft delete: el kardex y las recetas conservan el producto.
        $product->delete();

        return redirect()->route('products.index')->with('success', 'Producto eliminado del catálogo.');
    }

    /**
     * Buscador de productos para formularios (movimientos, recetas, insumos):
     * existencia por sucursal y lotes disponibles.
     */
    public function search()
    {
        $products = Product::with(['stocks', 'batches' => fn ($q) => $q->available()->orderByRaw('expires_at is null')->orderBy('expires_at')])
            ->where('active', true)
            ->search(request('q'))
            ->orderBy('name')
            ->limit(15)
            ->get();

        return response()->json($products->map(fn (Product $p) => [
            'id' => $p->id,
            'name' => $p->name,
            'label' => $p->label,
            'code' => $p->code,
            'barcode' => $p->barcode,
            'unit' => $p->unit_label,
            'track_batches' => $p->track_batches,
            'purchase_price' => (float) $p->purchase_price,
            'sale_price' => (float) $p->sale_price,
            'stock' => $p->stockIn(),
            'stocks' => $p->stocks->mapWithKeys(fn ($s) => [$s->branch_id => (float) $s->quantity]),
            'batches' => $p->batches->map(fn (ProductBatch $b) => [
                'id' => $b->id,
                'branch_id' => $b->branch_id,
                'lot' => $b->lot,
                'expires_at' => $b->expires_at?->format('d/m/Y'),
                'expired' => $b->isExpired(),
                'quantity' => (float) $b->quantity,
            ]),
        ]));
    }

    /** @return array<string, mixed> */
    protected function formData(): array
    {
        return [
            'categories' => ProductCategory::where('active', true)->orderBy('name')->get(),
            'branches' => Branch::where('active', true)->orderBy('name')->get(),
        ];
    }

    /** @return array<string, mixed> */
    protected function validateProduct(?Product $product = null): array
    {
        $companyId = app(Tenancy::class)->id();
        $unique = fn (string $column) => Rule::unique('products', $column)
            ->where('company_id', $companyId)->whereNull('deleted_at')->ignore($product?->id);

        return request()->validate([
            'product_category_id' => ['nullable', Rule::exists('product_categories', 'id')->where('company_id', $companyId)],
            'code' => ['nullable', 'string', 'max:40', $unique('code')],
            'barcode' => ['nullable', 'string', 'max:60', $unique('barcode')],
            'name' => 'required|string|max:160',
            'description' => 'nullable|string|max:2000',
            'brand' => 'nullable|string|max:80',
            'presentation' => 'nullable|string|max:120',
            'unit' => ['required', Rule::in(array_keys(Product::UNITS))],
            'purchase_price' => 'required|numeric|min:0|max:9999999',
            'sale_price' => 'required|numeric|min:0|max:9999999',
            'min_stock' => 'required|numeric|min:0|max:9999999',
            'image' => 'nullable|image|max:4096',
        ], [
            'code.unique' => 'Ya existe un producto con ese código interno.',
            'barcode.unique' => 'Ya existe un producto con ese código de barras.',
        ], [
            'name' => 'nombre',
            'purchase_price' => 'precio de compra',
            'sale_price' => 'precio de venta',
            'min_stock' => 'stock mínimo',
        ]);
    }

    protected function storeImage(?UploadedFile $file): ?string
    {
        return $file?->store('products/' . app(Tenancy::class)->id(), 'public');
    }
}
