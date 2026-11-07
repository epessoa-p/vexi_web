<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Jaula, canil o espacio de internación. */
class Kennel extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = ['company_id', 'branch_id', 'name', 'notes', 'active'];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    public function hospitalizations(): HasMany
    {
        return $this->hasMany(Hospitalization::class);
    }
}
