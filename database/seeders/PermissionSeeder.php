<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

/**
 * Permisos granulares, con slug `modulo.accion`.
 *
 * Los módulos que no figuran en Plan::PERMISSION_MODULE_FEATURES son
 * ADMINISTRATIVOS: siempre disponibles, sin depender del plan contratado.
 * Los que sí figuran (cajas, appointments…) exigen además su módulo de plan.
 * Ver ARQUITECTURA §6.2.
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            'companies' => ['label' => 'Empresas', 'article' => 'las empresas'],
            'plans' => ['label' => 'Planes', 'article' => 'los planes'],
            'subscriptions' => ['label' => 'Suscripciones', 'article' => 'las suscripciones', 'actions' => ['view', 'edit']],
            'users' => ['label' => 'Usuarios', 'article' => 'los usuarios'],
            'roles' => ['label' => 'Roles', 'article' => 'los roles'],
            'cargos' => ['label' => 'Cargos', 'article' => 'los cargos'],
            'personal' => ['label' => 'Personal', 'article' => 'el personal'],
            'branches' => ['label' => 'Sucursales', 'article' => 'las sucursales'],
            'cajas' => ['label' => 'Cajas', 'article' => 'las cajas'],
            'clients' => ['label' => 'Clientes', 'article' => 'los clientes y propietarios'],
            'pets' => ['label' => 'Mascotas', 'article' => 'las mascotas y su ficha clínica'],
            'species' => ['label' => 'Especies y razas', 'article' => 'el catálogo de especies y razas'],
            'services' => ['label' => 'Servicios', 'article' => 'el catálogo de servicios'],
            // Depende del módulo 'agenda' del plan (Plan::PERMISSION_MODULE_FEATURES).
            'appointments' => ['label' => 'Citas', 'article' => 'la agenda, las citas y la sala de espera'],
            // Depende del módulo 'consultas' del plan. Incluye recetas, procedimientos y seguimientos.
            'consultations' => ['label' => 'Consultas', 'article' => 'las consultas y sus recetas'],
            // Dependen del módulo 'vacunas' del plan. 'vaccinations' incluye las desparasitaciones.
            'vaccinations' => ['label' => 'Vacunas y desparasitaciones', 'article' => 'las vacunas y desparasitaciones aplicadas'],
            'vaccines' => ['label' => 'Catálogo de vacunas', 'article' => 'el catálogo de vacunas y sus protocolos'],
            // Dependen del módulo 'inventario' del plan. Los movimientos no se borran: se anulan con otro movimiento.
            'products' => ['label' => 'Productos', 'article' => 'los productos, medicamentos y sus categorías'],
            'inventory' => ['label' => 'Inventario', 'article' => 'las existencias y movimientos (entradas, salidas, ajustes)', 'actions' => ['view', 'create', 'edit']],
            // Ventas dependen del módulo 'pos'; caja y gastos, del módulo 'caja'.
            // sales.edit = anular y devolver; sales.create también registra abonos.
            'sales' => ['label' => 'Ventas', 'article' => 'las ventas, cobros y cuentas por cobrar', 'actions' => ['view', 'create', 'edit']],
            'cash' => ['label' => 'Caja', 'article' => 'la apertura, movimientos y cierre de caja', 'actions' => ['view', 'create']],
            'expenses' => ['label' => 'Gastos', 'article' => 'los gastos'],
            // Dependen del módulo 'compras'. purchases.edit = recibir, pagar, cerrar y anular.
            'suppliers' => ['label' => 'Proveedores', 'article' => 'los proveedores'],
            'purchases' => ['label' => 'Compras', 'article' => 'las compras, recepciones y pagos a proveedores', 'actions' => ['view', 'create', 'edit']],
            // Dependen del módulo 'laboratorio'. lab_requests.edit cambia el estado; lab_results.edit carga resultados.
            'lab_requests' => ['label' => 'Solicitudes de laboratorio', 'article' => 'las solicitudes de estudios', 'actions' => ['view', 'create', 'edit']],
            'lab_results' => ['label' => 'Resultados de laboratorio', 'article' => 'los resultados de los estudios', 'actions' => ['edit']],
            'lab_studies' => ['label' => 'Catálogo de estudios', 'article' => 'el catálogo de estudios y sus valores de referencia'],
            // Dependen del módulo 'hospitalizacion'. create = admitir y dar de alta; edit = cuidados, tomas y costos.
            'hospitalizations' => ['label' => 'Hospitalización', 'article' => 'las internaciones, sus cuidados y el alta', 'actions' => ['view', 'create', 'edit']],
            'kennels' => ['label' => 'Espacios de internación', 'article' => 'las jaulas y espacios de internación'],
            // Dependen del módulo 'estadisticas'. view = clínica y stock; finance = dinero: ingresos, ventas, utilidad y valor del inventario.
            'reports' => ['label' => 'Reportes', 'article' => 'los reportes', 'actions' => ['view', 'finance', 'export']],
        ];

        $verbs = [
            'view' => ['Ver', 'Consultar'],
            'create' => ['Crear', 'Dar de alta'],
            'edit' => ['Editar', 'Modificar'],
            'delete' => ['Eliminar', 'Dar de baja'],
            'finance' => ['Ver finanzas en', 'Consultar ingresos, ventas y utilidad en'],
            'export' => ['Exportar', 'Exportar a Excel'],
        ];

        foreach ($modules as $module => $meta) {
            foreach ($meta['actions'] ?? array_keys($verbs) as $action) {
                [$verb, $description] = $verbs[$action];

                Permission::updateOrCreate(
                    ['slug' => "{$module}.{$action}"],
                    [
                        'name' => "{$verb} {$meta['label']}",
                        'module' => $module,
                        'description' => "{$description} {$meta['article']}",
                    ]
                );
            }
        }
    }
}
