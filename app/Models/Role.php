<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Catálogo GLOBAL de roles: el mismo rol "manager" se asigna en cualquier
 * empresa y otorga los mismos permisos. Ver ARQUITECTURA §5.1.
 */
class Role extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'slug', 'description'];

    protected $casts = [
        'deleted_at' => 'datetime',
    ];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    /**
     * Usuarios que tienen este rol en alguna empresa.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'company_user', 'role_id', 'user_id')
                    ->withPivot('company_id', 'active');
    }

    public function cargos(): HasMany
    {
        return $this->hasMany(Cargo::class);
    }
}
