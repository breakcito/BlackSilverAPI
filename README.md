# BlackSilver API - Arquitectura y Convenciones Técnicas

Backend robusto diseñado para la gestión integral de operaciones mineras y logística ERP. Construido sobre **Laravel 12** y **PHP 8.2+**, utiliza una arquitectura modular híbrida personalizada que garantiza escalabilidad, desacoplamiento y alta reutilización de código.

---

## 🏗 Arquitectura del Sistema: "Hybrid Modular Architecture"

El proyecto no sigue la estructura monolítica estándar de Laravel. Implementa un patrón de **Módulos Independientes** que conviven con una **Capa Global de Datos y Servicios** para evitar duplicidad de código.

### 1. `app/Modules/` - El Núcleo de Dominio

Cada carpeta dentro de `Modules` representa un micro-servicio interno enfocado en un proceso de negocio específico (ej. `Cotizaciones`, `RequerimientosAlmacenAtencion`).

- **Endpoints (`XEndpoints.php`)**: Definición manual de rutas tipo API.
- **Controllers**: Validadores de entrada (Requests) y orquestadores del flujo.
- **Services**: Contenedores de la **lógica de negocio exclusiva del módulo**.
- **Data (Local)**: Consultas SQL específicas que solo atañen al módulo.

### 2. Capa Global: Servicios y Datos Compartidos (`app/Services/` y `app/Data/`)

Existen entidades transversales que son requeridas constantemente por múltiples módulos (ej. consultar un producto, actualizar el stock). Para no repetir código, esta lógica se centraliza globalmente:

- **Maestros Corporativos**: `AlmacenesData/Service`, `EmpleadosData/Service`, `EmpresasData/Service`.
- **Catálogos y Terceros**: `ProductosData/Service`, `UnidadesMedidaData/Service`, `ProveedoresData/Service`, `PersonalExternoData/Service`.
- **Motor de Inventario (Crítico)**:
    - `LotesProductosData/Service`: **Punto de entrada principal** para registrar movimientos de inventario. Este servicio encapsula la actualización del stock físico y la inyección automática en el Kardex. Todo módulo que altere el inventario debe invocarlo (`update_stock` o `crear_lote`) para evitar duplicar lógica de auditoría.
    - `KardexProductosData/Service`: Servicio interno gestionado automáticamente por Lotes. Mantiene el registro de auditoría (doble saldo) de la empresa, pero **no debe ser invocado directamente** por los módulos para no corromper el flujo.
- **Estructura UI**: `MenuNavData/Service` (para navegación basada en permisos).

### 3. El Controlador Auxiliar (`AuxController.php` y `AuxEndpoints.php`)

Para evitar que cada módulo defina sus propios endpoints redundantes (ej. pedir la lista de almacenes desde Cotizaciones y nuevamente desde Requerimientos), se implementó el ecosistema `Aux`.

- **Propósito**: Actúa como un _Hub_ centralizado para peticiones de catálogos y selects recurrentes (`get_almacenes`, `get_productos`, `get_lotes_disponibles`).
- **Regla de Consumo**: El Frontend (y específicamente el `AuxService` de React) debe apuntar siempre a los endpoints auxiliares `/api/aux/...` para popular modales de búsqueda o filtros genéricos.

### 4. Estandarización de Estados (`app/Shared/Enums/`)

El sistema hace un uso intensivo de _Backed Enums_ de PHP para evitar "magic strings" y mantener integridad de datos.

- **Regla de Ordenamiento Estricta**: Cada tabla o proceso operativo físico (Ej. `Entrega`, `Recepcion`, `Solicitud`, `OrdenCompra`) **debe tener su propio Enum dedicado** en su respectiva subcarpeta dentro de `Shared/Enums`.
- **Ejemplo**: Las recepciones usan `EstadoOCTransRecepcion`, y las transferencias usan `EstadoOCTransferencia`. No se reciclan Enums genéricos entre procesos distintos para evitar choques lógicos.

---

## 📦 Módulos del Sistema

Los módulos de la API están organizados bajo los mismos dominios funcionales que el Frontend:

### Configuración y Operaciones

- `almacenes`
- `concesiones`
- `contratistas` (API)
- `empresas`
- `minas-labores`

### Inventarios y Maestros

- `productos`
- `categorias`
- `lotes-productos`
- `kardex-productos`

### Gestión de Compras

- `proveedores`
- `cotizaciones`
- `ordenes-compra`
- `ordenes-compra-recepcion-transferencias`

### Flujos de Almacén (Salidas)

- `requerimientos-almacen` & `atencion`
- `solicitudes-reabastecimiento` & `atencion`
- `prestamos-almacen` & `atencion`

### Personal y Accesos

- `personal` (Empleados y Contratistas)
- `organigrama`
- `login`
- `perfil`
- `cuentas`
- `roles`

---

## 🏛️ Reglas Críticas de Desarrollo

1. **Consistencia de Respuestas**: Toda respuesta de cualquier servicio o controlador debe retornar obligatoriamente a través de los helpers globales `ApiResponse::success()` o `ApiResponse::error()`.
2. **Prohibición de Rutas Redundantes**: No crear endpoints en módulos específicos para devolver listados recurrentes. Para eso existe `AuxController`.
3. **Atomicidad Lógica**: Cualquier proceso que implique registros o actualizaciones (como `KardexProductosService`) debe estar envuelta en un `DB::transaction()`.


## ⚙️ Ejecución

1. Configurar el archivo `.env`
2. `composer install`
3. `php artisan key:generate`
4. `php artisan storage:link` (Crítico para que los archivos multimedia y adjuntos sean públicos).
5. `php artisan serve`
