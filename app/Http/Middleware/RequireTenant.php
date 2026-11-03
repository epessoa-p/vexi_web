<?php

namespace App\Http\Middleware;

use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige una empresa activa. Lo usan los módulos clínicos y operativos
 * (clientes, mascotas, catálogos…): sus datos siempre son de UNA veterinaria,
 * así que en Modo Global el superadmin debe "entrar como" una empresa.
 */
class RequireTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app(Tenancy::class)->hasTenant()) {
            return $next($request);
        }

        if (auth()->user()?->is_super_admin) {
            return redirect()->route('companies.index')
                ->with('error', 'Entra como una veterinaria para gestionar sus datos.');
        }

        return redirect()->route('select-company')
            ->with('error', 'Selecciona una empresa para continuar.');
    }
}
