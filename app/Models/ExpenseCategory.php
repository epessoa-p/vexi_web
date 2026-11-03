<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Categoría de gastos (§9.6), editable por cada veterinaria. */
class ExpenseCategory extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'name', 'active'];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
