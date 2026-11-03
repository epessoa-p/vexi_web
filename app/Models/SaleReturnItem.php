<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleReturnItem extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'sale_return_id', 'sale_item_id', 'quantity', 'amount'];

    protected $casts = [
        'quantity' => 'decimal:2',
        'amount' => 'decimal:2',
    ];

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }
}
