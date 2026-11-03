<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseReceptionItem extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'purchase_reception_id', 'purchase_item_id', 'quantity', 'lot', 'expires_at', 'manufactured_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'expires_at' => 'date',
        'manufactured_at' => 'date',
    ];

    public function purchaseItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseItem::class);
    }
}
