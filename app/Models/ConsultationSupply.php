<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Insumo o medicamento usado en la consulta; se descuenta del stock al finalizarla. */
class ConsultationSupply extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'consultation_id', 'product_id', 'quantity', 'notes', 'sort'];

    protected $casts = [
        'quantity' => 'decimal:2',
    ];

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
