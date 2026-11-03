<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Proveedor (§8.1). */
class Supplier extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $fillable = [
        'company_id', 'name', 'tax_id', 'contact_name', 'phone', 'email', 'address', 'notes', 'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        return $term === '' ? $query : $query->where(fn ($q) => $q
            ->where('name', 'like', "%{$term}%")
            ->orWhere('tax_id', 'like', "%{$term}%")
            ->orWhere('contact_name', 'like', "%{$term}%"));
    }
}
