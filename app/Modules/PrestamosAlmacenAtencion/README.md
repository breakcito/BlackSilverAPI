# Módulo API: Atención de Préstamos (Deep Dive)

Gestiona la confirmación de salida de materiales por préstamo y la recepción física del retorno del stock.

## 🛠 Componentes del Módulo

### 1. Controlador (`AtencionController`, `EntregaController`, `RecepcionReposicionController`)

- **`registrar_entrega`**:
    - **Validación**: Valida los lotes de salida del almacén prestamista y las cantidades.
- **`registrar_recepcion`**:
    - **Validación**: Procesa el ingreso del material devuelto, permitiendo crear nuevos lotes o aumentar existentes.

### 2. Servicios Operativos

#### `EntregaService`

- Gestiona el despacho físico inicial del préstamo.
- Rebaja el stock del almacén prestamista y marca el préstamo como "En Despacho".

#### `RecepcionesReposicionService` (Cierre de Transacción)

- **`registrar_recepcion`**:
    - **Lógica Inversa**: Es el proceso espejo de la reposición. Mientras que la reposición es una salida del almacén deudor, esta recepción es el **Ingreso** al almacén acreedor.
    - **Gestión de Lotes**: Permite que el almacén que recibe el stock de vuelta decida si lo integra a un lote que ya tenía o si crea uno nuevo (por ejemplo, si el producto volvió con una fecha de vencimiento distinta).
    - **Consistencia de Kardex**: Registra un movimiento de **Ingreso por Reposición**, restaurando el balance del inventario original.
    - **Control de Estados**:
        - `actualizar_estados_post_recepcion`: Calcula si lo recibido cuadra con lo repuesto. Si todo coincide, cierra el detalle y eventualmente la cabecera del Vale de Reposición.

### 3. Capa de Datos (`RecepcionesReposicionData`)

- **SQL de Cierre**:
    - `get_cantidad_recepcionada_total_base_detalle`: Sumariza todas las recepciones parciales para validar el cierre del ciclo de préstamo.

## ⚙️ Reglas de Negocio

- **Retorno de Valor**: El sistema asegura que el stock regrese al almacén que lo prestó originalmente, manteniendo la trazabilidad del costo mediante el Kardex vinculado.
- **Evidencias Obligatorias**: Se recomienda capturar fotos del estado del material al recibirlo de vuelta, especialmente si hay incidencias.

## 📂 Esquema de Base de Datos Relacionada

- `prestamo_almacen_recepcion_reposicion`: Registro del ingreso físico por devolución.
- `prestamo_almacen_reposicion`: El documento de salida que originó este ingreso.
- `kardex_producto`: Registro del movimiento contable de entrada.
