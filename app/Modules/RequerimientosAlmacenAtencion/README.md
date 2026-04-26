# Módulo API: Atención de Requerimientos (Deep Dive)

Este módulo es el brazo operativo del almacén. Gestiona la aprobación técnica, la entrega física y el impacto en el inventario.

## 🛠 Componentes del Módulo

### 1. Controladores (`AtencionController`, `EntregaController`)

- **`save_decision_detalle`**:
    - **Validación**: Array de IDs de detalles y el `nuevo_estado` (Aprobado/Rechazado).
    - **Proceso**: Delega al `AtencionService` para actualizar la voluntad del almacenero sobre el pedido.
- **`save_entrega`**:
    - **Validación**: IDs de empleados (entrega/recibe), ID de requerimiento, y un array complejo de `detalles` que incluye `id_lote_producto`, `cantidad_base` y `cantidad_requerimiento`.

### 2. Servicios Operativos

#### `AtencionService`

- **`cambiar_estado_detalle`**:
    - **Transaccional**: Si un item es aprobado, el requerimiento cabecera cambia automáticamente a estado "En Despacho".
    - **Timeline**: Registra un hito en el historial del producto con la glosa correspondiente al estado y el comentario de la decisión.

#### `EntregaService` (Complejo)

- **`registrar_entrega` (El Proceso más Crítico)**:
    - **Pre-Validación**: Realiza un **Eager Loading** de todos los lotes involucrados en una sola consulta. Valida que haya stock suficiente en cada uno antes de iniciar cualquier escritura.
    - **Consistencia de Inventario**:
        1. Rebaja el stock físico del lote (`id_lote_producto`).
        2. Genera un asiento de **Salida** en el Kardex vinculado al Vale de Entrega.
        3. Incrementa la `cantidad_entregada` en el detalle del requerimiento.
    - **Cierre Inteligente**: Si la cantidad entregada acumulada alcanza lo solicitado, marca el item automáticamente como "Completado".
    - **Log de Eventos**: Genera múltiples entradas en el Timeline:
        - "En Despacho" (si es la primera entrega).
        - "Nueva Entrega" (especificando la cantidad enviada).
        - "Completado" (si se cubrió el total).

### 3. Capa de Datos (`EntregasData`, `RequerimientosData`)

- **SQL de Despacho**:
    - `get_resumen_requerimientos`: Consulta optimizada que muestra solo los pedidos pendientes de atención para el almacén del usuario.
- **Persistencia**:
    - `crear_entrega`: Registra la cabecera del Vale de Salida con su propio correlativo (`VAL-XXXX`).

## ⚙️ Reglas de Negocio en Inventario

- **Unidad Base vs Unidad Medida**: El servicio siempre valida y rebaja el stock en `cantidad_base` para asegurar que el Kardex sea consistente independientemente de la presentación (cajas, bolsas, etc.).
- **Trazabilidad de Lote**: Obliga a que cada salida esté vinculada a un lote específico, permitiendo el seguimiento de fechas de vencimiento.

## 📂 Esquema de Base de Datos Relacionada

- `requerimiento_almacen_entrega`: Cabecera del vale de salida.
- `requerimiento_almacen_entrega_detalle`: Items vinculados a lotes específicos.
- `kardex_producto`: Historial de movimientos de stock.
- `lote_producto`: Saldos actuales por serie/lote.
