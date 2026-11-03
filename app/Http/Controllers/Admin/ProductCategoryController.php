<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use App\Support\Tenancy;
use Illuminate\Validation\Rule;

/** Categorías de productos (§7.2), editables en línea. */
class ProductCategoryController extends Controller
{
    public function index()
    {
        return view('admin.product-categories.index', [
            'categories' => ProductCategory::withCount('products')->orderBy('name')->get(),
        ]);
    }

    public function store()
    {
        $validated = request()->validate([
            'name' => ['required', 'string', 'max:80', $this->uniqueName()],
        ], ['name.unique' => 'Esa categoría ya existe.'], ['name' => 'nombre']);

        ProductCategory::create($validated + ['active' => true]);

        return back()->with('success', 'Categoría creada.');
    }

    public function update(ProductCategory $category)
    {
        $validated = request()->validate([
            'name' => ['required', 'string', 'max:80', $this->uniqueName($category)],
        ], ['name.unique' => 'Esa categoría ya existe.'], ['name' => 'nombre']);

        $category->update($validated + ['active' => request()->boolean('active')]);

        return back()->with('success', 'Categoría actualizada.');
    }

    public function destroy(ProductCategory $category)
    {
        if ($category->products()->withTrashed()->exists()) {
            return back()->withErrors(['error' => 'La categoría tiene productos. Desactívala en lugar de eliminarla.']);
        }

        $category->delete();

        return back()->with('success', 'Categoría eliminada.');
    }

    protected function uniqueName(?ProductCategory $category = null)
    {
        return Rule::unique('product_categories', 'name')
            ->where('company_id', app(Tenancy::class)->id())
            ->ignore($category?->id);
    }
}
