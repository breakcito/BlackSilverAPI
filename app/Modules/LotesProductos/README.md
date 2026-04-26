# Módulo API: Lotes de Productos (Deep Dive)

Este módulo gestiona la trazabilidad física y el control de inventario por series, fechas de vencimiento y saldos granulares.

## 🛠 Componentes del Módulo

### 1. Controlador (`LotesController`)

- **`crear_lote`**:
    - **Validación**: Valida cabecera técnica del lote, incluyendo `contenido_por_presentacion` y `stock_inicial`.
    - **Regla Temporal**: Obliga a que la `fecha_vencimiento` sea mayor o igual a la `fecha_hora_ingreso`.
- **`ajustar_stock`**:
    - **Validación**: Requiere el `nuevo_stock` y `nuevo_stock_base` (ambos >= 0) y un motivo opcional.

### 2. Servicio de Inventario (`LotesService`)

- **`crear_lote`**:
    - **Correlativo**: Genera un número único (`LOT-XXXX`) basado en el almacén.
    - **Impacto Inicial**: Si el lote nace con stock (> 0), el servicio registra automáticamente un movimiento de **Ingreso** en el Kardex con el origen "Nuevo Lote".
- **`ajustar_stock` (Corrección Manual)**:
    - **Transaccional**: Compara el stock actual contra el nuevo para determinar la dirección del movimiento.
    - **Detección Automática**:
        - Si la diferencia es positiva, registra un **Ingreso**.
        - Si es negativa, registra una **Salida**.
    - **Generación de Glosa**: Si el usuario no provee un motivo, el servicio construye uno automáticamente describiendo la cantidad de unidades retiradas o aumentadas.
    - **Persistencia**: Actualiza el balance del lote y deja huella en el Kardex bajo el tipo "Ajuste de Stock".

### 3. Capa de Datos (`LotesData`, `LotesProductosData`)

- **Queries de Impresión**:
    - `get_info_to_ticket`: Obtiene datos técnicos del producto (nombre, unidad, categoría) cruzados con el lote para la generación de códigos QR o etiquetas.
- **Saldos**:
    - `update_stock`: Método atómico para actualizar `stock_actual` y `stock_actual_base`.

## ⚙️ Reglas de Negocio

- **Cálculo de Cantidad Base**: Todo movimiento de stock se multiplica por el `contenido_por_presentacion`. Esto permite que si ingresas "1 caja de 10 unidades", el sistema sepa que tiene 10 unidades base para despachos parciales.
- **Auditoría de Ajustes**: No se permite que el nuevo stock sea igual al actual, forzando a que cada ajuste represente un cambio real documentado.

## 📂 Esquema de Base de Datos Relacionada

- `lote_producto`: Tabla maestra de saldos por serie/fecha.
- `kardex_producto`: Registro de cada ajuste o creación de lote.
- `producto`: Información técnica asociada.
