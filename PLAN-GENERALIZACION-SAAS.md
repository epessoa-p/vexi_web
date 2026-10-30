# Plan SaaS — VR Motors → producto para NEGOCIOS DE MOTOS (repuestos + taller)

El sistema se venderá como SaaS para el **ecosistema moto**: tiendas de **repuestos de moto**,
**talleres de motos** (mecánica), y opcionalmente **concesionarias** (venta de motos) y
**alquiler de motos**. La "generalización" ya **no** busca volverlo multi-rubro (moto+auto);
al contrario: **la identidad moto es el producto** y se conserva/refuerza. Generalizar aquí =
"vendible a cualquier negocio de motos, en cualquier país", quitando lo que era a medida de
**VR Motors** o de **Bolivia**, pero manteniendo el dominio moto.

> **Ya hecho:**
> - Moneda **por empresa** (`companies.currency`) + helper `money()`/`currency_symbol()` en todo el sistema.
> - **Unidades de medida** como catálogo por empresa (CRUD + selector en producto + import).
> - **Planes configurables** (CRUD) + **overrides por empresa** (cupos y módulos).
> - **White-label** (colores + logo por empresa) y **catálogo público por sucursal** (QR/PDF).
> - **Límites de plan** al crear (sucursales/productos/usuarios) con aviso al pulsar.
> - **Personal ↔ sucursal** (branch por empleado) + recepción de taller toma la sucursal del personal.
> - **Modelos compatibles** de producto vía `moto_models` (estructurado); recepción de vehículo
>   elige el **modelo desde el catálogo de motos** (autocompletado + auto-relleno de marca/cc).

---

## P1 — Alto valor para vender a cualquier taller/tienda de motos

### 1. Coherencia de identidad MOTO (dejar de "genericizar")
- **Contexto:** en el trabajo previo (cuando el objetivo era moto+auto) se cambiaron algunas
  etiquetas/íconos a genéricos (`bi-car-front`, "Modelos", ejemplos con "Corolla 2015"). Siendo
  un SaaS **de motos**, conviene volver a una identidad moto **consistente**.
- **Propuesta (bajo esfuerzo, cosmético):**
  - Ícono de vehículo/moto **uniforme** (p. ej. `bi-bicycle`) en: ficha de producto (modelos),
    filtro "Modelo" del POS, recepción de taller, vehículos del cliente.
  - Ejemplos neutros pero **moto** en la plantilla/ayuda de import (ej. "CG 150, FZ-S, Pulsar 200"),
    no autos.
  - Wording: "Modelos" / "Modelos compatibles" está bien; mantenerlo, sin "auto".
- **Esfuerzo:** bajo.

### 2. Presets de plan por tipo de negocio moto
- **Contexto:** el gating por plan (módulos + cupos) ya existe y es el **lever ideal** para los
  distintos negocios moto. Falta que los planes **por defecto** reflejen esos arquetipos.
- **Propuesta:** definir planes semilla orientados a moto:
  - **Repuestos** → Inventario, Ventas (POS), Caja.
  - **Taller** → lo anterior + Taller (OT/mecánica) + Compras.
  - **Concesionaria** → + Venta de motos + Fidelización.
  - **Alquiler** → + Alquiler de motos.
  - **Full** → todos los módulos.
- Actualizar `PlanSeeder` y/o crearlos desde el panel de Planes (ya editable).
- **Esfuerzo:** bajo.

### 3. Tipos de documento por país (NIT/CI/RUC) — ❌ DESCARTADO
- **Decisión:** el software es **netamente para Bolivia**. NIT/CI/RUC se quedan tal cual. Si en el
  futuro se vende a otro país, se **copia** el sistema y se adapta para ese país.

---

## P2 — Localización y onboarding

### 4. Zona horaria y formato de fecha/número — ❌ DESCARTADO (Bolivia)
- **Decisión:** al ser netamente para Bolivia, se mantiene `America/La_Paz` y formato `es`. No se
  hace por empresa. (Se adaptaría solo en una copia para otro país.)

### 5. Catálogo de marcas de moto semilla para empresas nuevas
- **Contexto:** una empresa nueva arranca con `moto_brands`/`moto_models` vacíos; el catálogo se
  llena a mano o por import. Para un taller/tienda de motos, un set base acelera el onboarding.
- **Propuesta:** al crear empresa (o en un seeder por empresa), sembrar **marcas de moto** comunes
  (Honda, Yamaha, Suzuki, Bajaj, KTM, Kawasaki, TVS, Keeway, Vento, Italika, …). Modelos se
  agregan por import/uso. Consistente con cómo sembramos unidades por empresa.
- **Esfuerzo:** bajo.

### 6. Auditoría de gating por módulo (defensa en profundidad) — ✅ HECHO
- **Hallazgo:** las **rutas** ya respetan el plan (`plan:X` en todos los módulos) y el **menú**
  también, vía la directiva `@module('X')` que comprueba `planAllows`. No había hueco general.
- **Fix real (el caso que surgió):** el módulo `motos` mezclaba el **catálogo** (marcas/modelos,
  que el inventario/POS necesitan) con la **venta de motos**. Se **desacopló**: `moto-brands.*` y
  `moto-models.*` pasaron a `plan:inventory` y su menú a la sección **Inventario**; la venta de motos
  (unidades/ventas/entregas/garantías) sigue en `plan:motos` (concesionaria). Así una tienda de
  repuestos/taller administra el catálogo (coherente con que el import crea modelos y el POS los
  filtra) sin tener el módulo de concesionaria.

---

## P3 — Limpieza / deuda técnica

### 7. Referencias "VR Motors" en scripts SQL históricos — ✅ HECHO
- Eran **comentarios de cabecera** (`-- VR Motors — …`) en 34 scripts `database/sql/*.sql`, sin
  efecto en runtime y sin datos reales. Se reemplazaron por **`Rodex`** (nombre del producto).
- La **empresa real** "VR MOTORS" en la BD **no se tocó** (es dato del cliente).

### 8. Semillas y textos de ejemplo
- Planes/seeders con precios en Bs pensados para Bolivia y textos de ejemplo variados. Parametrizar
  moneda/precio y usar ejemplos **moto** neutros de país.

### 9. Marca de plataforma (SCZ SOFT) — ya OK
- `config/brand.php` centraliza nombre/logo de la plataforma, `env`-configurable. Nada urgente.

---

## Recomendación de arranque (mayor valor / menor esfuerzo)
1. **Coherencia de identidad moto** (P1-1) — barato y refuerza el producto.
2. **Presets de plan por tipo de negocio moto** (P1-2) — aprovecha lo ya construido.
3. **Marcas de moto semilla + tipos de documento** (P2-5, P1-3) — onboarding y multi-país.
4. Luego **zona horaria/locale por empresa** (P2-4) para multi-país completo.

## Verificación (por cada punto)
- Identidad: recorrer POS, inventario, taller y confirmar íconos/ejemplos **moto** consistentes.
- Planes: crear un plan "Taller" y una empresa con él; ver que solo aparezcan sus módulos.
- Marcas semilla: crear empresa nueva y confirmar que trae marcas de moto listas para usar.
- Documento/timezone: cambiarlos por empresa y verlos aplicados en formularios y fechas.
- `php artisan view:cache` + `php -l` sin errores tras cada tanda.
