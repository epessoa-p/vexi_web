<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Support\Cash;
use App\Support\Staff;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** Gastos (§9.6). Pagados desde la caja, también salen de ella. */
class ExpenseController extends Controller
{
    public function index()
    {
        $from = request('from') ? Carbon::parse(request('from'))->startOfDay() : now()->startOfMonth();
        $to = request('to') ? Carbon::parse(request('to'))->endOfDay() : now()->endOfMonth();

        $query = Expense::with(['category', 'paymentMethod', 'user'])
            ->whereBetween('spent_at', [$from->toDateString(), $to->toDateString()])
            ->when(request('category_id'), fn ($q, $id) => $q->where('expense_category_id', $id));

        return view('admin.expenses.index', [
            'expenses' => (clone $query)->latest('spent_at')->latest('id')->paginate(25)->withQueryString(),
            'total' => (float) (clone $query)->sum('amount'),
            'byCategory' => (clone $query)->get()->groupBy(fn ($e) => $e->category?->name ?? 'Sin categoría')
                ->map(fn ($group) => (float) $group->sum('amount'))->sortDesc(),
            'categories' => ExpenseCategory::orderBy('name')->get(),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function create()
    {
        return view('admin.expenses.create', $this->formData() + ['expense' => null]);
    }

    public function store()
    {
        $validated = $this->validateExpense();

        DB::transaction(function () use ($validated) {
            $expense = Expense::create(collect($validated)->except(['receipt', 'from_cash'])->all() + [
                'receipt' => request()->file('receipt')?->store('expenses/' . app(Tenancy::class)->id(), 'public'),
            ]);

            // Pagado con el efectivo (u otro medio) de una caja abierta: sale de ella.
            if ($expense->cash_session_id) {
                Cash::record($expense->cashSession, 'expense', $expense->paymentMethod, -(float) $expense->amount,
                    "Gasto: {$expense->concept}", $expense);
            }
        });

        return redirect()->route('expenses.index')->with('success', 'Gasto registrado.');
    }

    public function edit(Expense $expense)
    {
        return view('admin.expenses.edit', $this->formData() + ['expense' => $expense]);
    }

    public function update(Expense $expense)
    {
        $validated = $this->validateExpense($expense);

        $data = collect($validated)->except(['receipt', 'from_cash', 'cash_session_id', 'amount', 'payment_method_id'])->all();

        // Lo que ya salió de la caja no se cambia: para corregir el monto, se elimina y se registra de nuevo.
        if (! $expense->cash_session_id) {
            $data += collect($validated)->only(['amount', 'payment_method_id'])->all();
        }

        if (request()->hasFile('receipt')) {
            if ($expense->receipt) {
                Storage::disk('public')->delete($expense->receipt);
            }
            $data['receipt'] = request()->file('receipt')->store('expenses/' . app(Tenancy::class)->id(), 'public');
        }

        $expense->update($data);

        return redirect()->route('expenses.index')->with('success', 'Gasto actualizado.');
    }

    public function destroy(Expense $expense)
    {
        // Lo que salió de una caja ya cerrada forma parte de un arqueo: no se toca.
        if ($expense->cash_session_id && ! $expense->cashSession?->isOpen()) {
            return back()->withErrors(['error' => 'El gasto salió de una caja ya cerrada: no se puede eliminar.']);
        }

        DB::transaction(function () use ($expense) {
            // Si salió de una caja todavía abierta, el dinero vuelve a ella.
            if ($expense->cash_session_id) {
                Cash::record($expense->cashSession, 'income', $expense->paymentMethod, (float) $expense->amount,
                    "Anula gasto: {$expense->concept}", $expense);
            }

            $expense->delete();
        });

        return redirect()->route('expenses.index')->with('success', 'Gasto eliminado.');
    }

    /** @return array<string, mixed> */
    protected function formData(): array
    {
        return [
            'categories' => ExpenseCategory::where('active', true)->orderBy('name')->get(),
            'paymentMethods' => PaymentMethod::where('active', true)->orderBy('sort')->get(),
            'sessions' => Cash::enabled() ? Cash::openSessions() : collect(),
            'professionals' => Staff::professionals(app(Tenancy::class)->company()),
            'branches' => Branch::where('active', true)->orderBy('name')->get(),
        ];
    }

    /** @return array<string, mixed> */
    protected function validateExpense(?Expense $expense = null): array
    {
        $companyId = app(Tenancy::class)->id();
        $exists = fn (string $table) => Rule::exists($table, 'id')->where('company_id', $companyId);

        $validated = request()->validate([
            'expense_category_id' => ['nullable', $exists('expense_categories')],
            'branch_id' => ['nullable', $exists('branches')],
            'concept' => 'required|string|max:255',
            // Un gasto que salió de la caja conserva su monto (el campo llega deshabilitado).
            'amount' => [$expense?->cash_session_id ? 'nullable' : 'required', 'numeric', 'min:0.01', 'max:9999999'],
            'spent_at' => 'required|date|before_or_equal:today',
            'payment_method_id' => ['nullable', 'required_with:cash_session_id', $exists('payment_methods')],
            'cash_session_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer'],
            'receipt' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'notes' => 'nullable|string|max:2000',
        ], [
            'payment_method_id.required_with' => 'Indica con qué medio se pagó desde la caja.',
        ], [
            'concept' => 'concepto',
            'amount' => 'monto',
            'spent_at' => 'fecha',
            'receipt' => 'comprobante',
        ]);

        if (! $expense && ! empty($validated['cash_session_id'])) {
            Cash::sessionFor((int) $validated['cash_session_id']);   // valida que esté abierta
        } else {
            $validated['cash_session_id'] = $expense?->cash_session_id;
        }

        if (! empty($validated['user_id']) && ! Staff::belongsTo((int) $validated['user_id'], app(Tenancy::class)->company())) {
            $validated['user_id'] = auth()->id();
        }
        $validated['user_id'] ??= auth()->id();

        return $validated;
    }
}
