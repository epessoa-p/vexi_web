<?php

namespace App\Support;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Profesionales de una veterinaria a los que se puede asignar una cita.
 *
 * User es un modelo global, así que aquí se acota a mano a los miembros
 * activos de la empresa. Los veterinarios van primero en la lista.
 */
class Staff
{
    public const CLINICAL_ROLES = ['veterinarian', 'vet_assistant'];

    /** @return Collection<int, User> con ->role_name y ->is_vet */
    public static function professionals(?Company $company): Collection
    {
        if (! $company) {
            return collect();
        }

        return User::query()
            ->select('users.*', 'roles.slug as role_slug', 'roles.name as role_name')
            ->join('company_user', 'company_user.user_id', '=', 'users.id')
            ->leftJoin('roles', 'roles.id', '=', 'company_user.role_id')
            ->where('company_user.company_id', $company->id)
            ->where('company_user.active', true)
            ->where('users.active', true)
            ->get()
            ->each(fn (User $user) => $user->is_vet = $user->role_slug === 'veterinarian')
            ->sortBy([
                fn ($a, $b) => (int) $b->is_vet <=> (int) $a->is_vet,
                fn ($a, $b) => strcmp($a->name, $b->name),
            ])
            ->values();
    }

    /** ¿El usuario es miembro activo de la empresa? (para validar asignaciones) */
    public static function belongsTo(?int $userId, ?Company $company): bool
    {
        return $userId && $company
            && self::professionals($company)->contains('id', $userId);
    }
}
