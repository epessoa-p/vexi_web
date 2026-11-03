<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Gasto de la veterinaria (§9.6). Si se pagó desde la caja, también sale de ella. */
class Expense extends Model
{
    use SoftDeletes, BelongsToCompany;

    protected $fillable = [
        'company_id', 'branch_id', 'expense_category_id', 'concept', 'amount', 'spent_at',
        'payment_method_id', 'cash_session_id', 'user_id', 'receipt', 'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'spent_at' => 'date',
        'deleted_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getReceiptUrlAttribute(): ?string
    {
        return $this->receipt ? asset('storage/' . $this->receipt) : null;
    }
}
