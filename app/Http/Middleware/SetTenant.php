<?php

namespace App\Http\Middleware;

use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fija la empresa activa de la petición. Corre en todo el grupo `web`.
 *
 *   - Invitado           → sin empresa (fail-closed). No rompe el login:
 *                          User y Company son modelos globales.
 *   - Superadmin         → Modo Global (sin filtro), salvo que haya elegido
 *                          "entrar como" una empresa concreta.
 *   - Usuario de empresa → la de su sesión, o la única que tenga.
 *
 * Ver ARQUITECTURA §4.1.
 */
class SetTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenancy = app(Tenancy::class);
        $user = auth()->user();

        if (! $user) {
            $tenancy->set(null);

            return $next($request);
        }

        if ($user->is_super_admin) {
            $impersonated = session('current_company_id');

            if ($impersonated) {
                $tenancy->set((int) $impersonated);
            } else {
                $tenancy->unrestrict();
            }
        } else {
            $companyId = session('current_company_id')
                ?? $user->activeCompanies()->value('companies.id');

            if ($companyId) {
                session(['current_company_id' => $companyId]);
            }

            $tenancy->set($companyId ? (int) $companyId : null);
        }

        $company = $tenancy->company();

        // Fechas y horas (agenda, registros clínicos) en la hora local de la
        // veterinaria: se guardan y se muestran en su zona horaria.
        if ($company?->timezone && in_array($company->timezone, timezone_identifiers_list(), true)) {
            config(['app.timezone' => $company->timezone]);
            date_default_timezone_set($company->timezone);
        }

        // Los controladores y las vistas leen de aquí, sin repetir la consulta.
        $request->attributes->set('tenant_company', $company);
        view()->share('tenantCompany', $company);
        view()->share('globalMode', $tenancy->isUnrestricted());

        return $next($request);
    }
}
