<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

/**
 * Permisos granulares, con slug `modulo.accion`.
 *
 * Todos los módulos de aquí son ADMINISTRATIVOS: no figuran en
 * Plan::PERMISSION_MODULE_FEATURES, así que están siempre disponibles y no
 * dependen del plan contratado. Ver ARQUITECTURA §6.2.
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
        ];

        $verbs = [
            'view' => ['Ver', 'Consultar'],
            'create' => ['Crear', 'Dar de alta'],
            'edit' => ['Editar', 'Modificar'],
            'delete' => ['Eliminar', 'Dar de baja'],
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
