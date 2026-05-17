# Módulo API: Activos Fijos (Deep Dive)

Este módulo gestiona la creación, trazabilidad, y ciclo de vida operativo de los **Activos Fijos** de la empresa minera (vehículos, maquinaria pesada, herramientas de alto valor). A diferencia de los productos de inventario genéricos, cada Activo Fijo representa una unidad física única e identificable.

## 🛠 Componentes del Módulo

### 1. Controlador (`ActivosFijosController`)

- **`get_activos_disponibles`**: Retorna los activos fijos que están activos y disponibles en una mina/almacén determinado para ser receptores de consumo o transferencias.
- **`store`**: Registra un nuevo activo físico en el sistema. Realiza validaciones estrictas de marca, modelo, número de serie, y código interno.

### 2. Servicio de Negocio (`ActivosFijosService`)

- **`registrar_activo` (Transaccional)**:
    - **Generación Dinámica de Correlativo**: Lee el prefijo de producto mediante `ProductosData::get_producto_by_id` (ej. `TRC` para un Tractor).
    - Llama a `ActivosFijosData::get_nuevo_correlativo($prefijo)` para generar un correlativo único secuencial alineado al estándar de la empresa (ej. `TRC-00001`).
    - Encapsula la persistencia dentro de un `DB::transaction()` para garantizar la atomicidad en la inserción de registros del activo y su vinculación inicial con el stock.

### 3. Capa de Datos (`ActivosFijosData`)

- Encapsula las consultas directas a la tabla `activo_fijo`.
- **`get_nuevo_correlativo($prefijo)`**: Genera la secuencia matemática basada en el prefijo recibido para prevenir colisiones de códigos entre distintos tipos de activos fijos concurrentes.

---

## ⚙️ Reglas de Negocio

- **Prefijos Obligatorios**: Todo producto clasificado como `Activo Fijo` (`tipo_bien`) debe poseer obligatoriamente un `prefijo` registrado en la tabla `producto` para poder ser instanciado como un activo individual.
- **Vínculo con Labores**: El activo físico siempre opera bajo el ámbito de una **Mina** y sus **Labores**, permitiendo imputar consumos (ej. lubricantes, combustible) directamente a su hoja de vida operativa.

---

## 📂 Esquema de Base de Datos Relacionada

- `activo_fijo`: Tabla maestra que almacena los activos únicos (maquinarias, vehículos).
- `producto`: Registro maestro del cual nace el activo fijo (contiene la definición base y el prefijo).
- `marca_activo`: Catálogo de marcas de fabricantes.
- `kardex_producto`: Registra los movimientos de stock asociados al ingreso y despacho de este activo físico individual.
