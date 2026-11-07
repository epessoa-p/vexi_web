<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Línea de una venta: producto, servicio, consulta u otro concepto. */
class SaleItem extends Model
{
    use BelongsToCompany;

    public const TYPES = [
        'product' => 'Producto',
        'service' => 'Servicio',
        'consultation' => 'Consulta',
        'lab' => 'Laboratorio',
        'hospitalization' => 'Hospitalización',
        'other' => 'Otro',
    ];

    protected $fillable = [
        'company_id', 'sale_id', 'type', 'product_id', 'service_id', 'consultation_id', 'lab_request_id', 'hospitalization_id', 'description',
        'quantity', 'unit_price', 'discount', 'total', 'returned_quantity', 'affects_stock',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'returned_quantity' => 'decimal:2',
        'affects_stock' => 'boolean',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->withTrashed();
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function returnableQuantity(): float
    {
        return round((float) $this->quantity - (float) $this->returned_quantity, 2);
    }

    /** Precio efectivo por unidad, con el descuento de la línea repartido. */
    public function netUnitPrice(): float
    {
        return (float) $this->quantity > 0 ? round((float) $this->total / (float) $this->quantity, 4) : 0;
    }
}
