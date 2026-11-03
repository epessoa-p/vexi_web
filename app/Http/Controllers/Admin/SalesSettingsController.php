<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Support\Tenancy;
use Illuminate\Validation\Rule;

/**
 * Configuración comercial (§15.3): métodos de pago y categorías de gastos,
 * editables en línea.
 */
class SalesSettingsController extends Controller
{
    public function paymentMethods()
    {
        return view('admin.settings.payment-methods', [
            'methods' => PaymentMethod::orderBy('sort')->orderBy('name')->get(),
        ]);
    }

    public function storePaymentMethod()
    {
        PaymentMethod::create($this->validatePaymentMethod() + [
            'active' => true,
            'sort' => (int) PaymentMethod::max('sort') + 1,
        ]);

        return back()->with('success', 'Método de pago agregado.');
    }

    public function updatePaymentMethod(PaymentMethod $method)
    {
        $method->update($this->validatePaymentMethod() + ['active' => request()->boolean('active')]);

        return back()->with('success', 'Método de pago actualizado.');
    }

    public function expenseCategories()
    {
        return view('admin.settings.expense-categories', [
            'categories' => ExpenseCategory::withCount('expenses')->orderBy('name')->get(),
        ]);
    }

    public function storeExpenseCategory()
    {
        ExpenseCategory::create($this->validateExpenseCategory() + ['active' => true]);

        return back()->with('success', 'Categoría agregada.');
    }

    public function updateExpenseCategory(ExpenseCategory $category)
    {
        $category->update($this->validateExpenseCategory($category) + ['active' => request()->boolean('active')]);

        return back()->with('success', 'Categoría actualizada.');
    }

    /** @return array<string, mixed> */
    protected function validatePaymentMethod(): array
    {
        $validated = request()->validate([
            'name' => 'required|string|max:60',
            'type' => ['required', Rule::in(array_keys(PaymentMethod::TYPES))],
        ], [], ['name' => 'nombre']);

        return $validated + ['requires_reference' => request()->boolean('requires_reference')];
    }

    /** @return array<string, mixed> */
    protected function validateExpenseCategory(?ExpenseCategory $category = null): array
    {
        return request()->validate([
            'name' => ['required', 'string', 'max:80',
                Rule::unique('expense_categories', 'name')->where('company_id', app(Tenancy::class)->id())->ignore($category?->id)],
        ], ['name.unique' => 'Esa categoría ya existe.'], ['name' => 'nombre']);
    }
}
