# Módulo API: Cotizaciones (Deep Dive)

Es el núcleo del proceso de procura. Maneja comparativas multi-proveedor y la automatización de la compra.

## 🛠 Componentes del Módulo

### 1. Controlador (`CotizacionesController`)

- **`registrar_comparativo`**:
    - **Validaciones**: Estructura de arrays anidados (`productos`, `cotizaciones`, `detalles`). Valida que cada cotización tenga al menos una empresa compradora asignada.
- **`aprobar_cotizacion_parcial`**:
    - **Validaciones**: Requiere el ID de la empresa que figurará en la OC y el array de IDs de detalles aprobados.

### 2. Servicio (`CotizacionesService`)

- **`registrar_comparativo` (Complejo)**:
    - **Transaccionalidad**: Todo el proceso ocurre dentro de una `DB::transaction`. Si algo falla, no se crea nada.
    - **Paso 1**: Genera un nuevo correlativo de comparativo usando el `CorrelativoHelper`.
    - **Paso 2**: Registra los productos solicitados en el comparativo maestro.
    - **Paso 3**: Itera cada cotización de proveedor, calculando impuestos (IGV) según si el precio enviado es incluido o no.
    - **Paso 4**: **Auto-PO**: Si una cotización se marca como "Aprobada" desde el registro, dispara inmediatamente la lógica de creación de Orden de Compra.
- **`aprobar_cotizacion_parcial`**:
    - Cambia el estado de la cotización y marca los detalles seleccionados como "Aprobados" y el resto como "Rechazados".
    - Calcula el subtotal neto de los items aprobados, suma flete y otros gastos, y aplica el IGV configurado para generar la **Orden de Compra** final.
- **`listar`**: Realiza un **Eager Loading** manual mediante indexación de arrays para evitar el problema de consultas N+1 al agrupar Comparativos -> Cotizaciones -> Detalles -> Empresas.

### 3. Capa de Datos (`CotizacionesData`)

- **Queries Pesadas**: Utiliza SQL Raw para unir cotizaciones con proveedores y sus documentos de identidad (RUC/DNI) en una sola petición.
- **Correlativos**: Se integra con el modelo `Cotizacion` para obtener números secuenciales únicos por año.

## ⚙️ Lógica de Impuestos y Totales

El servicio implementa una lógica robusta de cálculo:

- Si `incluye_igv` es `true`: `Total_Antes = Base / (1 + IGV%)`.
- Si `incluye_igv` es `false`: `Monto_IGV = Base * IGV%`.
  Esto garantiza que la Orden de Compra refleje exactamente lo negociado con el proveedor.

## 📂 Esquema de Base de Datos Relacionada

- `comparativo`: Maestro del proceso de selección.
- `cotizacion`: Cabecera de la oferta del proveedor.
- `cotizacion_detalle`: Items, precios y tiempos ofrecidos.
- `cotizacion_empresa`: Empresas que pueden comprar basándose en esa cotización.
