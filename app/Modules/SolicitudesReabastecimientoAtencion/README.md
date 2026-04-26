# Módulo API: Atención de Solicitudes de Reabastecimiento (Deep Dive)

Gestiona la decisión logística sobre las solicitudes de reposición, permitiendo derivarlas a despachos directos o convertirlas en préstamos entre almacenes.

## 🛠 Componentes del Módulo

### 1. Controladores (`SolicitudesController`, `EntregaController`, `PrestamosController`)

- **`registrar_despacho`**:
    - **Validación**: Recibe el ID de solicitud y un array de items con sus respectivos lotes de salida.
- **`crear_prestamo`**:
    - **Validación**: ID de solicitud, ID del almacén que prestará el material (`id_almacen_prestamista`), y los detalles de cantidades.

### 2. Servicios de Negocio

#### `SolicitudesService`

- **`actualizar_estado_detalle`**:
    - **Decisión**: Permite aprobar o rechazar items de la solicitud.
    - **Efecto Dominó**: Si un item es aprobado, el sistema habilita la creación de Vales de Entrega o Solicitudes de Préstamo vinculadas.

#### `PrestamosService` (Conversión de Negocio)

- **`crear_prestamo`**:
    - **Validación de Stock Real**: Antes de crear el préstamo, el servicio consulta el stock físico **en tiempo real** del almacén prestamista. Si el stock cambió durante el proceso de llenado del formulario, aborta la transacción con un mensaje descriptivo.
    - **Integración**: Inserta logs de trazabilidad en la solicitud de reabastecimiento original ("Solicitando Préstamo") para que el solicitante sepa que su pedido ha sido derivado a otro almacén.
    - **Creación de Entidad**: Genera una cabecera en `prestamo_almacen` con su propio correlativo (`PRE-XXXX`).

#### `EntregaService`

- **`registrar_entrega`**:
    - Similar al módulo de Atención de Requerimientos, pero enfocado en el reabastecimiento logístico.
    - Rebaja stock mediante Kardex y actualiza la `cantidad_atendida` de la solicitud.

### 3. Capa de Datos (`PrestamosData`, `AuxData`)

- **Queries de Inteligencia**:
    - `get_almacenes_con_stock`: Localiza qué almacenes de la red cuentan con saldo positivo del producto solicitado para sugerir destinos de préstamo.
- **Vistas y Reportes**:
    - Utiliza SQL Raw para mostrar la trazabilidad cruzada entre Solicitud -> Préstamo -> Entrega.

## ⚙️ Reglas de Negocio

- **Prioridad de Abastecimiento**: El sistema prioriza el uso de stock excedente en otros almacenes (vía Préstamo) antes de generar nuevas compras externas, optimizando el inventario global de la mina.
- **Validación de Lote**: En despachos directos, el servicio valida que el lote seleccionado pertenezca al almacén que está atendiendo la solicitud.

## 📂 Esquema de Base de Datos Relacionada

- `reabastecimiento_entrega`: Cabecera del despacho logístico.
- `prestamo_almacen`: Documento generado cuando se deriva la atención a otro almacén.
- `lote_producto`: Origen del stock para la atención.
