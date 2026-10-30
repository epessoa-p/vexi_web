# Vexi — Panel web

SaaS multi-empresa para veterinarias. Cada veterinaria es un *tenant* aislado, con su
plan, su suscripción y sus propios datos.

## Requisitos

- PHP 8.2+
- MySQL 8
- Composer
- Node.js 20+ *(para compilar los estilos; sin él la app funciona igual,
  cargando Bootstrap desde el CDN — ver [Assets](#assets))*
- MySQL corriendo en el puerto que indique `DB_PORT` (en esta máquina, 3317)

## Instalación

```bash
composer install
cp .env.example .env          # ajusta DB_* y VEXI_*
php artisan key:generate
php artisan migrate:fresh --seed
npm install && npm run build  # opcional, ver Assets
php artisan serve
```

## Credenciales de demostración

Todas con la contraseña `Admin@1234`.

| Usuario | Papel |
|---|---|
| `superadmin@vexi.test` | Operador de la plataforma. Sin empresa: arranca en **Modo Global** y ve todas. |
| `admin@veterinariademo.test` | Administra Veterinaria Demo (plan Premium, activo). |
| `cajero@veterinariademo.test` | Permisos recortados: sirve para ver el filtrado en acción. |
| `admin@veterinariaprueba.test` | Administra Veterinaria Prueba (plan Básico en prueba, con un override de sucursales). |

Hay **dos** veterinarias sembradas a propósito: con una sola no se puede comprobar
a mano que el aislamiento funciona.

## Arquitectura

La referencia completa está en
[`../ARQUITECTURA-EMPRESAS-PLANES-PERMISOS.md`](../ARQUITECTURA-EMPRESAS-PLANES-PERMISOS.md).
En resumen, cada petición atraviesa cuatro capas en este orden:

```
¿La suscripción de la empresa está vigente?   → EnsureSubscriptionActive
    └─ ¿El plan incluye el módulo?            → CheckPlanModule       (plan:agenda, plan:pos…)
        └─ ¿El usuario tiene el permiso?      → CheckPermission       (branches.create…)
            └─ ¿Queda cupo en el plan?        → planLimitReached()    (solo al CREAR)
```

El **superadmin** (`users.is_super_admin`) salta las cuatro.

### Aislamiento entre empresas

`App\Support\Tenancy` guarda la empresa activa de la petición; el trait
`BelongsToCompany` + `CompanyScope` filtran e inyectan `company_id`
automáticamente en `Branch`, `Caja`, `Cargo` y `Personal`. El código de negocio
nunca escribe `where('company_id', …)`.

Tres estados, y la diferencia entre los dos últimos importa:

| Estado | Efecto |
|---|---|
| Con empresa | Filtra por ese `company_id`. |
| Sin empresa | **Fail-closed**: no devuelve nada. Nunca "todo". |
| Sin restricción | Modo Global del superadmin: sin filtro. |

Son modelos **globales** (nunca llevan el trait): `User`, `Company`, `Role`,
`Permission`, `Plan`, `Subscription`.

### Planes y suscripciones

Un plan define **módulos** (`Plan::MODULES`: agenda, consultas, vacunas,
laboratorio, hospitalizacion, inventario, compras, pos, caja, servicios,
estadisticas, recordatorios) y **límites**
(`max_users`, `max_branches`, `max_products`; `NULL` = ilimitado).

`Plan::PERMISSION_MODULE_FEATURES` decide qué módulo de permisos exige qué
módulo de plan. Lo que **no** figura ahí es administrativo y está siempre
disponible (empresas, usuarios, roles, cargos, personal, sucursales). Hoy la
única entrada es `cajas => caja`: la ruta lleva `plan:caja` y el menú oculta el
enlace, de modo que el guardia es real y no solo visual.

Cada empresa tiene **una** suscripción, cuyo estado se **deriva de las fechas**:
una vencida corta el acceso sola, sin que el operador toque nada. Dentro de
`grace_days` se puede consultar pero no registrar cambios.

Los **overrides** de la suscripción (`max_*_override`, `features_override`)
permiten ampliar o recortar el plan para una empresa concreta sin tocar el plan
global.

## Assets

Los estilos son Bootstrap 5.3 compilado con Vite desde `resources/scss/`.
`resources/views/layouts/partials/assets.blade.php` detecta si existe el
manifest de Vite: si no lo hay (máquina sin Node, clon recién hecho) carga
Bootstrap desde el CDN para que la app siga funcionando. En cuanto ejecutes
`npm run build`, Vite toma el control solo.

## Tests

```bash
php artisan test
```

Cubren el aislamiento entre empresas, el bypass del superadmin, los cuatro
estados de suscripción, los límites de plan con sus overrides y el filtrado por
permisos.

## Deuda técnica conocida

- **`CargoController` modifica roles globales.** Un administrador de una
  veterinaria que edite un cargo apuntando a un rol existente reescribe los
  permisos de ese rol **para todas las empresas**. Está contenido tras
  `check-permission:cargos.*`, pero hay que rediseñarlo (roles por empresa, o
  permisos de cargo desacoplados del rol).
- **Soft delete e índices únicos.** `users.email`, `roles.slug`,
  `companies.tax_id` y `plans.slug` son `unique` sin incluir `deleted_at`: un
  registro borrado bloquea ese valor para siempre.
- **Sin API todavía.** La app móvil (`../vexi_movil`) necesita
  `routes/api.php` + Sanctum + `X-Company-Id` + `GET /me`. El diseño de
  `Tenancy` y `SubscriptionGate` ya está preparado para ello.
