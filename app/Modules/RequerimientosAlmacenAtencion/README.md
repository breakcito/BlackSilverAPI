# Módulo API: Atención de Requerimientos (Deep Dive)

Este módulo representa el núcleo operativo y de despacho físico de almacenes. Gestiona la aprobación técnica de solicitudes, la entrega física estructurada, y la trazabilidad de consumo de recursos.

## 🛠 Componentes del Módulo

### 1. Controladores (`AtencionController`, `EntregaController`)

- **`save_decision_detalle`**: Registra la decisión técnica del almacenero (Aprobación/Rechazo) sobre un ítem específico del requerimiento.
- **`save_entrega`**:
    - **Validación de Vale**: Recibe los detalles de entrega física incluyendo `id_lote_producto`, `cantidad_base` y `cantidad_requerimiento`.

### 2. Servicios Operativos

#### `AtencionService`
- **`cambiar_estado_detalle`**:
    - **Transaccional**: Gestiona hitos en el Timeline y actualiza de forma automática las cabeceras a "En Despacho" ante la aprobación del primer ítem.

#### `EntregaService`
- **`registrar_entrega`**:
    - **Consistencia Matemática**: Disminuye stock físico del lote, inyecta salida en el Kardex y actualiza la cantidad acumulada entregada en la base de datos de manera atómica.

### 3. Capa de Datos (`EntregasData`, `RequerimientosData`, `RequerimientosDetalleData`)

- **`RequerimientosDetalleData::get_detalles_by_requerimiento`**:
    - **Trazabilidad por Bienes**: Incluye un join a la tabla `categoria` (`cat.clasificacion_bien`) para inyectar en tiempo real el `tipo_bien` del producto solicitado, permitiendo que la interfaz reaccione dinámicamente según la naturaleza de la mercadería.
    - **Trazabilidad por Destinos**: Recupera `id_activo_fijo_destino` y su respectivo `correlativo_activo_fijo_destino` para auditar a qué activo específico (ej. camión, pala mecánica) se le imputa el gasto del material.

---

## ⚙️ Reglas de Negocio y Restricciones UI

- **Auditoría Granular (Ítem por Ítem)**: Un requerimiento compuesto por múltiples ítems se procesa individualmente. Cada ítem guarda de forma aislada su propio estado, fecha de despacho, y responsable técnico.
- **Trazabilidad de Destino**: Si un producto suministra/abastece a otros activos, el sistema obliga al contratista a registrar el activo fijo de destino exacto (`id_activo_fijo_destino`) al momento de realizar el pedido.
- **Bloqueo de Unidad para Activos Fijos**: Si el contratista requiere un producto cuyo `tipo_bien` es un **Activo Fijo** (`TipoBien.ActivoFijo`), la interfaz bloquea el selector de Unidad de Medida en su unidad base propia (`id_unidad_medida_base`). Esto garantiza la consistencia física del inventario al evitar que activos fijos únicos se soliciten en empaques genéricos o fraccionados.
- **Trazabilidad en PDF e Impresión**: Los vales físicos de entrega y reportes en PDF inyectan el correlativo del activo destino en el formato `PARA: [Nombre de Producto] [CORRELATIVO_ACTIVO]` para total transparencia ante fiscalizaciones.

---

## 📂 Esquema de Base de Datos Relacionada

- `requerimiento_almacen`: Cabecera del pedido operativo minero.
- `requerimiento_almacen_detalle`: Ítems solicitados con enlaces a `id_activo_fijo_destino`.
- `activo_fijo`: Maquinaria que consume el suministro.
- `categoria`: Clasificación del bien para reglas operativas de bloqueo.
- `kardex_producto`: Historial de egreso de inventarios.
