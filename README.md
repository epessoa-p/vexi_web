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
php artisan storage:link        # fotos de mascotas (disco public)
npm install && npm run build  # opcional, ver Assets
php artisan serve
```

## Credenciales de demostración

Todas con la contraseña `Admin@1234`.

| Usuario | Papel |
|---|---|
| `vet@veterinariademo.test` | Profesional de Veterinaria Demo (rol Veterinario): las citas demo están a su nombre. |
| `superadmin@vexi.test` | Operador de la plataforma. Sin empresa: arranca en **Modo Global** y ve todas. |
| `admin@veterinariademo.test` | Administra Veterinaria Demo (plan Premium, activo). |
| `cajero@veterinariademo.test` | Cajero: ventas, caja y gastos, sin acceso clínico. Sirve para ver el filtrado en acción. |
| `admin@veterinariaprueba.test` | Administra Veterinaria Prueba (plan Básico en prueba, con un override de sucursales). |

Hay **dos** veterinarias sembradas a propósito (Veterinaria Demo trae además 3
propietarios y 5 mascotas de ejemplo): con una sola no se puede comprobar
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
automáticamente en `Branch`, `Caja`, `Cargo`, `Personal`, `Client`, `Pet`,
`PetWeight`, `Species` y `Breed`. El código de negocio
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

## Módulos clínicos

| Módulo | Rutas | Permisos | Plan |
|---|---|---|---|
| Clientes / propietarios | `/clientes` | `clients.*` | administrativo (siempre) |
| Mascotas y ficha del paciente | `/mascotas` | `pets.*` | administrativo (siempre) |
| Especies y razas | `/configuracion/especies` | `species.*` | administrativo (siempre) |
| Servicios | `/configuracion/servicios` | `services.*` | administrativo (siempre) |
| Agenda y citas | `/agenda` | `appointments.*` | `agenda` |
| Sala de espera | `/sala-de-espera` | `appointments.*` | `agenda` |
| Consultas, recetas y seguimiento | `/consultas` | `consultations.*` | `consultas` |
| Vacunación (calendario, aplicaciones, desparasitaciones, carnet) | `/vacunacion`, `/mascotas/{id}/carnet` | `vaccinations.*` | `vacunas` |
| Catálogo de vacunas y protocolos | `/configuracion/vacunas` | `vaccines.*` | `vacunas` |
| Productos y kardex | `/inventario/productos` | `products.*` | `inventario` |
| Movimientos (entradas, salidas, ajustes, transferencias) y vencimientos | `/inventario/movimientos`, `/inventario/vencimientos` | `inventory.*` | `inventario` |
| Categorías de productos | `/configuracion/categorias-productos` | `products.*` | `inventario` |
| Punto de venta, ventas, devoluciones y anulación | `/ventas/nueva`, `/ventas` | `sales.*` | `pos` |
| Cuentas por cobrar | `/cuentas-por-cobrar` | `sales.view` (abonos: `sales.create`) | `pos` |
| Métodos de pago | `/configuracion/metodos-de-pago` | `sales.*` | `pos` |
| Caja (apertura, movimientos, arqueo y cierre) | `/caja` | `cash.*` | `caja` |
| Gastos y sus categorías | `/gastos`, `/configuracion/categorias-gastos` | `expenses.*` | `caja` |
| Proveedores | `/proveedores` | `suppliers.*` | `compras` |
| Compras, recepción y pagos a proveedores | `/compras` | `purchases.*` | `compras` |
| Cuentas por pagar | `/cuentas-por-pagar` | `purchases.view` | `compras` |
| Perfil profesional (matrícula, firma) | `/mi-perfil-profesional` | — (cada usuario el suyo) | — |

Las rutas clínicas llevan el middleware `tenant` (`RequireTenant`): sus datos
son siempre de **una** veterinaria, así que en Modo Global el superadmin debe
"entrar como" una empresa.

Cada veterinaria nueva arranca con un catálogo de especies y razas
(`App\Support\ClinicalCatalog`), idempotente y editable; el botón
«Restaurar catálogo base» agrega lo que falte sin tocar lo editado.

### Agenda

- El calendario usa FullCalendar 6 desde jsDelivr (solo en `/agenda`) y trabaja
  en modo UTC con horas "de pared": la hora que se ve es la hora local de la
  veterinaria, sin importar la zona del navegador.
- La zona horaria de la app es `America/La_Paz` por defecto (`APP_TIMEZONE`), y
  `SetTenant` aplica la de la veterinaria activa en cada petición.
- Un profesional no puede tener dos citas activas que se crucen; las
  canceladas y las de "no asistió" liberan el horario.
- Estados: Pendiente → Confirmada → En espera → En atención → Atendida, más
  Cancelada y No asistió. Las transiciones válidas están en
  `Appointment::TRANSITIONS`.

### Consultas

- Se inician desde la cita o la sala de espera ("Iniciar consulta" / "Atender"):
  la cita pasa a *En atención* y queda *Atendida* al finalizar la consulta.
  También se puede abrir una consulta sin cita desde la ficha de la mascota.
- Mientras está *En proceso* se guarda como borrador. Para finalizarla hace
  falta un diagnóstico principal. Una consulta **finalizada no se edita ni se
  elimina**: la evolución posterior se registra como seguimiento.
- Al finalizar, el peso medido pasa al historial de peso de la mascota.
- La receta (`/consultas/{id}/receta`) es una vista A5 imprimible; el botón
  "Imprimir / guardar como PDF" usa el diálogo del navegador. Lleva la
  matrícula, la especialidad y la firma del perfil profesional del veterinario.

### Vacunas y desparasitaciones

- Cada vacuna del catálogo tiene un protocolo: N dosis de serie cada X días y
  refuerzo cada Y días. Al registrar una aplicación, la próxima dosis se
  calcula sola (`Vaccine::nextDueDate`); se puede cambiar o dejar sin programar.
- Una dosis pendiente deja de contar cuando se registra una aplicación
  posterior de la misma vacuna (`Vaccination::pendingDue`). En
  desparasitaciones, una "interna y externa" cubre a las dos, pero una externa
  no cubre una interna (`Deworming::pendingDue`).
- Intervalos por defecto de desparasitación y ventana de "próximas" en
  `config/vexi.php` (`deworming`, `health_alert_days`).

### Farmacia e inventario

- La existencia es **por sucursal** y solo cambia a través de `App\Support\Stock`
  (`receive`, `consume`, `adjust`, `transfer`, `reverse`), que en la misma
  transacción deja el movimiento en el kardex con su saldo, actualiza el
  caché `product_stocks` y los lotes. Si la veterinaria no tiene sucursales,
  se crea "Sucursal principal" en el primer movimiento.
- Los movimientos son **inmutables**: un error se corrige con un ajuste o con
  una anulación (`Stock::reverse`), nunca borrando.
- Productos con "controlar lotes": exigen lote al entrar; las salidas consumen
  primero el lote que vence antes (FEFO) y nunca uno vencido, salvo que se
  elija a propósito (p. ej. al darlo de baja).
- Integración clínica (solo si el plan incluye `inventario`):
  - Medicamentos de la receta vinculados a un producto → botón *Dispensar* en
    la consulta finalizada (no deja entregar sin stock).
  - *Insumos utilizados* en la consulta → se descuentan al finalizarla. Como
    ya se usaron, admiten faltante y la existencia queda en negativo, visible.
  - Vacuna del catálogo vinculada a un producto → al aplicarla sale una dosis
    (del lote anotado o por FEFO) y, sin lote anotado, la aplicación toma el
    del frasco usado. Lo mismo con desparasitaciones. Borrar el registro
    devuelve la unidad con un movimiento de anulación.
- El límite `max_products` del plan se comprueba al crear productos.

### Ventas y caja

- Toda venta pasa por `App\Support\Sales` (`create`, `addPayment`,
  `returnItems`, `cancel`), que en una sola transacción mantiene coherentes la
  venta, el stock (kardex) y la caja. Las ventas no se borran: se anulan.
- **Qué descuenta stock lo decide el servidor:** un producto de mostrador sí;
  uno que viene de la consulta (insumo o medicamento dispensado) ya salió del
  inventario y solo se cobra. Una consulta se cobra una sola vez
  (`consultations.sale_id`); al anular la venta queda otra vez pendiente.
- **Pagos:** pago mixto; si se entrega de más, el excedente es vuelto y solo
  puede salir del efectivo. Con saldo pendiente la venta es a crédito y exige
  cliente; los abonos posteriores entran por la caja como "Cobro de deuda".
- **Devoluciones y anulación:** los productos vuelven al **mismo lote** del que
  salieron (`Stock::restoreFrom`). El importe se descuenta primero del saldo
  pendiente y el resto se reembolsa desde una caja abierta.
- **Caja:** con el módulo `caja`, cobrar exige un turno abierto. El arqueo
  compara el efectivo contado con el esperado (saldo inicial + movimientos en
  efectivo); los demás métodos se informan por separado. Un turno cerrado no
  admite más movimientos, y un gasto que salió de él ya no se puede eliminar.
- Numeración correlativa por veterinaria (`V-000001`). El comprobante es un
  ticket interno de 80 mm, no una factura.

### Compras y proveedores

- Toda compra pasa por `App\Support\Purchases` (`create`, `receive`,
  `closeWithDifferences`, `addPayment`, `cancel`), que mantiene coherentes la
  compra, el stock y la caja. Numeración correlativa por veterinaria (`C-000001`).
- Totales: subtotal de líneas (cada una con su descuento) − descuento general,
  más el **impuesto** (% configurable por compra).
- **Recepción** parcial o total: lo recibido entra al stock de la sucursal de
  la compra con su lote y vencimiento, al costo real de la línea, que pasa a
  ser el precio de compra del producto. No se recibe más de lo pendiente.
- **Cerrar con diferencias**: lo que no llegó deja de esperarse y el saldo con
  el proveedor se recalcula sobre lo recibido (mismo descuento e impuesto, en
  proporción).
- **Pagos**: parciales o totales, nunca por encima del saldo; si salen de una
  caja abierta quedan como egreso en su arqueo. Una compra solo se anula si no
  tiene recepciones ni pagos.
