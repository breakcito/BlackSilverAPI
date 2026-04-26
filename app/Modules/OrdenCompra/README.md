# Módulo API: Órdenes de Compra (Deep Dive)

Gestiona la formalización de adquisiciones, el seguimiento de entregas y el historial de cumplimiento de los proveedores.

## 🛠 Componentes del Módulo

### 1. Controlador (`OrdenCompraController`)

- **`get_ordenes`**: Lista las cabeceras de OC con filtros temporales (mes/año).
- **`get_detalles`**: Recupera los items de una OC, incluyendo precios pactados, cantidades y almacenes de destino.

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

## ⚙️ Reglas de Negocio

- **Relación 1:N**: Un comparativo de precios puede generar múltiples Órdenes de Compra (una por cada proveedor seleccionado en la comparativa).
- **Control de Despacho**: Cada item en la OC tiene su propio `tipo_despacho` (Envío Directo, Recojo en Tienda) y `tiempo_entrega_dias` para el cálculo de penalidades por retraso.

## 📂 Esquema de Base de Datos Relacionada

- `orden_compra`: Cabecera del compromiso de compra.
- `orden_compra_detalle`: Items, precios y destinos de entrega.
- `log_orden_compra_detalle`: Trazabilidad completa del pedido.
- `proveedor`: Beneficiario de la orden.
