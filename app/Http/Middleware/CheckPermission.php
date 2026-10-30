<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, ...$permissions): Response
    {
        if (!auth()->check()) {
            return redirect('login');
        }

        $user = auth()->user();

        // Super admin siempre puede pasar
        if ($user->is_super_admin) {
            return $next($request);
        }

        // La empresa activa ya la resolvió SetTenant; no se vuelve a consultar.
        $company = $request->attributes->get('tenant_company');
        if (!$company) {
            return redirect()->route('select-company')
                ->with('error', 'Selecciona una empresa para continuar.');
        }

        // Verificar si el usuario tiene alguno de los permisos requeridos
        foreach ($permissions as $permission) {
            if ($user->hasPermissionInCompany($permission, $company)) {
                return $next($request);
            }
        }

        return response()->view('errors.403', [], 403);
    }
}
