# Módulo API: Productos (Deep Dive)

Gestiona el catálogo maestro de materiales, herramientas e insumos de la organización.

## 🛠 Componentes del Módulo

### 1. Controlador (`ProductosController`)

- **`crear_producto`**:
    - **Validación**: Valida `id_categoria`, `id_unidad_medida_base` y parámetros de perecibilidad.
- **`get_productos`**: Lista el catálogo con filtros por categoría.

### 2. Servicio de Catálogo (`ProductosService`)

- **`crear_producto`**:
    - **Unicidad**: Valida que no existan productos con el mismo nombre para evitar confusiones en inventario.
    - **Lógica de Alerta de Vencimiento**:
        - Si el producto es marcado como `es_perecible`, el servicio calcula automáticamente los `dias_espera_vencimiento`.
        - Transforma unidades humanas (semanal, mensual, anual) a días calendario (factor 7, 30, 365).
        - Este valor es utilizado por el sistema de alertas del almacén para notificar cuando un producto está próximo a caducar según su lote.
    - **Consistencia**: Asegura que si un producto no es perecible, los campos de tiempo de espera se limpien en la base de datos.

### 3. Capa de Datos (`ProductosData`, `UnidadesMedidaData`)

- **SQL de Integración**:
    - `get_productos`: Consulta que integra el nombre de la categoría y la unidad de medida base para facilitar la visualización en el frontend sin peticiones adicionales.
- **Unidades Compartidas**:
    - Consume `UnidadesMedidaData` desde la capa compartida (`App\Data`) para proveer la lista de unidades (unidades, kg, metros, etc.) permitidas.

## ⚙️ Reglas de Negocio

- **Unidad Base Inmutable**: Una vez creado el producto, su unidad de medida base debe mantenerse para no corromper el historial del Kardex.
- **Control Auditable**: Los productos marcados como `es_auditable` activan flujos de aprobación adicionales en el módulo de Requerimientos y Compras.

## 📂 Esquema de Base de Datos Relacionada

- `producto`: Maestro de items.
- `categoria`: Clasificación del bien.
- `unidad_medida`: Unidades de despacho y almacenamiento.
