# Módulo API: Órdenes de Compra (Deep Dive)

Gestiona la formalización de adquisiciones, el seguimiento de entregas y el historial de cumplimiento de los proveedores.

## 🛠 Componentes del Módulo

### 1. Controlador (`OrdenCompraController`)

- **`get_ordenes`**: Lista las cabeceras de OC con filtros temporales (mes/año).
- **`get_detalles`**: Recupera los items de una OC, incluyendo precios pactados, cantidades y almacenes de destino.
- **`registrar_transferencia`**: Endpoint `POST` para capturar la salida de stock desviado.

### 2. Servicios de Abastecimiento

#### `OrdenCompraService`

- Actúa como la interfaz de consulta para el estado de las compras. Permite visualizar el avance de las entregas y los incidentes reportados por el almacén receptor.

#### Integración con `CotizacionesService` (Auto-PO)

- **Generación Automática**: Las Órdenes de Compra no se crean manualmente. Nacen del proceso de aprobación en el módulo de **Cotizaciones**.
- **Lógica de Herencia**:
    - Al aprobar un comparativo, el sistema transfiere automáticamente toda la inteligencia recolectada: precios base, contenido de presentación, tiempos de entrega y condiciones de pago.
    - Se genera un correlativo único (`OC-XXXX`) por almacén.
- **Trazabilidad Inicial**: Cada detalle de la OC se crea con un estado "Pendiente" y genera su primer hito en el log de seguimiento, vinculándolo al empleado que aprobó el comparativo.

### 3. Capa de Datos (`OrdenCompraData`, `OrdenesCompraData`)

- **Queries de Seguimiento**:
    - `get_seguimiento`: Consulta el historial de eventos (`log_orden_compra_detalle`) permitiendo saber exactamente cuándo se aprobó, cuándo se despachó y cuándo llegó a mina.
- **Persistencia**:
    - `crear_detalle_orden`: Inserta los items vinculándolos al producto y la unidad de medida pactada.

## 🚚 Gestión de Transferencias (Corrección de Desvíos)

Este submódulo permite la trazabilidad de materiales que llegaron a un almacén central o incorrecto y deben ser enviados a su destino final.

### Lógica de Negocio y Valor
-   **Propósito**: Regularizar el inventario sin anular recepciones. Permite que un almacén actúe como "puerto" o "centro de distribución" temporal para productos destinados a unidades mineras remotas.
-   **Impacto en Stock**: El registro de una transferencia genera una **salida física inmediata** del almacén de origen, reduciendo el stock de los lotes seleccionados.
-   **Kardex**: Se registra un movimiento de tipo **"Entrega"** (Salida), manteniendo la trazabilidad desde la recepción original.

### Implementación Técnica
1.  **Entidades**:
    -   `OrdenCompraTransferencia`: Almacena la cabecera (origen, destino, responsable de la salida, transportista/receptor externo).
    -   `OrdenCompraTransferenciaDetalle`: Vincula cada item transferido con su `id_recepcion_detalle` de origen.
2.  **Integridad**: El sistema valida que la suma de cantidades transferidas no exceda nunca la cantidad originalmente recepcionada en dicho lote.
3.  **Servicio de Transferencia**: Centraliza la lógica de reducción de stock, inserción en Kardex y actualización de estados, asegurando que la operación sea atómica mediante transacciones de base de datos.

## 📂 Esquema de Base de Datos Relacionada

- `orden_compra`: Cabecera del compromiso de compra.
- `orden_compra_detalle`: Items, precios y destinos de entrega.
- `log_orden_compra_detalle`: Trazabilidad completa del pedido.
- `orden_compra_recepcion`: Registro de ingresos.
- `orden_compra_transferencia`: Registro de salidas correctivas entre almacenes.
- `proveedor`: Beneficiario de la orden.
