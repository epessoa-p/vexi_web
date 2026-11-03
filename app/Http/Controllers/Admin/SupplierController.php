<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\Supplier;

/** Proveedores (§8.1). */
class SupplierController extends Controller
{
    public function index()
    {
        $suppliers = Supplier::withCount('purchases')
            ->withSum(['purchases as payable' => fn ($q) => $q->payable()], 'balance')
            ->search(request('q'))
            ->when(request('status') === 'inactive', fn ($q) => $q->where('active', false), fn ($q) => $q->where('active', true))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.suppliers.index', compact('suppliers'));
    }

    public function create()
    {
        return view('admin.suppliers.create', ['supplier' => null]);
    }

    public function store()
    {
        $supplier = Supplier::create($this->validateSupplier() + ['active' => request()->boolean('active', true)]);

        // Desde el formulario de compra se vuelve a él con el proveedor elegido.
        if (request('return') === 'purchase') {
            return redirect()->route('purchases.create', ['supplier_id' => $supplier->id])->with('success', 'Proveedor creado.');
        }

        return redirect()->route('suppliers.show', $supplier)->with('success', 'Proveedor creado.');
    }

    public function show(Supplier $supplier)
    {
        return view('admin.suppliers.show', [
            'supplier' => $supplier,
            'purchases' => Purchase::where('supplier_id', $supplier->id)->latest('purchased_at')->latest('id')->paginate(15),
            'payable' => (float) Purchase::where('supplier_id', $supplier->id)->payable()->sum('balance'),
            'bought' => (float) Purchase::where('supplier_id', $supplier->id)->where('status', '!=', 'cancelled')->sum('total'),
        ]);
    }

    public function edit(Supplier $supplier)
    {
        return view('admin.suppliers.edit', compact('supplier'));
    }

    public function update(Supplier $supplier)
    {
        $supplier->update($this->validateSupplier() + ['active' => request()->boolean('active')]);

        return redirect()->route('suppliers.show', $supplier)->with('success', 'Proveedor actualizado.');
    }

    public function destroy(Supplier $supplier)
    {
        // Soft delete: sus compras conservan el proveedor.
        $supplier->delete();

        return redirect()->route('suppliers.index')->with('success', 'Proveedor eliminado.');
    }

    /** @return array<string, mixed> */
    protected function validateSupplier(): array
    {
        return request()->validate([
            'name' => 'required|string|max:160',
            'tax_id' => 'nullable|string|max:32',
            'contact_name' => 'nullable|string|max:120',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:2000',
            'active' => 'sometimes|boolean',
        ], [], ['name' => 'nombre o razón social', 'tax_id' => 'NIT']);
    }
}
