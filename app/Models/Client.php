<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Cliente / propietario. Puede tener varias mascotas. */
class Client extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    public const DOCUMENT_TYPES = [
        'CI' => 'Cédula de identidad',
        'NIT' => 'NIT',
        'PASAPORTE' => 'Pasaporte',
        'OTRO' => 'Otro',
    ];

    protected $fillable = [
        'company_id',
        'first_name',
        'last_name',
        'document_type',
        'document_number',
        'phone',
        'whatsapp',
        'email',
        'address',
        'notes',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    public function pets(): HasMany
    {
        return $this->hasMany(Pet::class)->orderBy('name');
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /** Enlace wa.me con solo dígitos; null si no hay número. */
    public function getWhatsappUrlAttribute(): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) ($this->whatsapp ?: ''));

        return $digits ? "https://wa.me/{$digits}" : null;
    }

    /** Busca por nombre, apellido, documento, teléfono o correo. */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $like = "%{$term}%";

            // "Ana Pérez": cada palabra debe aparecer en el nombre o el apellido.
            $q->where(function (Builder $names) use ($term) {
                foreach (preg_split('/\s+/', $term) as $word) {
                    $names->where(fn (Builder $w) => $w
                        ->where('first_name', 'like', "%{$word}%")
                        ->orWhere('last_name', 'like', "%{$word}%"));
                }
            })
              ->orWhere('document_number', 'like', $like)
              ->orWhere('phone', 'like', $like)
              ->orWhere('whatsapp', 'like', $like)
              ->orWhere('email', 'like', $like);
        });
    }
}
