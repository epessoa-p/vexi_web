<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Empleado de una empresa (veterinario, auxiliar, recepcionista, cajero…). */
class Personal extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    /** Eloquent pluralizaría a "personals"; la tabla es "personal". */
    protected $table = 'personal';

    protected $fillable = [
        'company_id',
        'cargo_id',
        'user_id',
        'full_name',
        'id_number',
        'phone',
        'email',
        'address',
        'hire_date',
        'notes',
        'active',
    ];

    protected $casts = [
        'hire_date' => 'date',
        'active' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    public function cargo(): BelongsTo
    {
        return $this->belongsTo(Cargo::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
