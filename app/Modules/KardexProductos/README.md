# Módulo API: Kardex de Productos (Deep Dive)

Provee la auditoría completa de movimientos de inventario, permitiendo reconstruir la historia de saldos de cada producto por almacén.

## 🛠 Componentes del Módulo

### 1. Controlador (`KardexController`)

- **`get_almacenes`**:
    - **Validación de Seguridad**: Implementa un filtro basado en permisos. Si el usuario tiene el privilegio `almacenes_all`, puede ver todos los movimientos; de lo contrario, el servicio restringe la vista solo a los almacenes donde el empleado es el responsable designado.
- **`get_resumen_kardex`**: Recupera el historial de movimientos de un período específico.

### 2. Servicio de Auditoría (`KardexService`)

- Actúa como el orquestador de reportes de inventario.
- **Lógica de Visibilidad**: Centraliza la lógica de quién puede ver qué stock, garantizando que los almaceneros solo auditen su propia operación a menos que tengan permisos administrativos.

### 3. Capa de Datos Compartida (`App\Data\KardexProductosData`)

_Nota: Aunque este módulo es de consulta, la lógica de escritura reside en la capa compartida, utilizada por casi todos los servicios operativos._

- **`registrar_kardex` (Método Maestro)**:
    - **Trazabilidad de Origen**: Obliga a registrar el `id_origen` y `tipo_origen` (Entrega, Recepción, Ajuste, Reposición). Esto permite que, desde el Kardex, el usuario pueda navegar directamente al documento físico que generó el movimiento.
    - **Consistencia de Doble Saldo**: Registra siempre el stock anterior y el nuevo, tanto en unidades de presentación como en unidades base, permitiendo auditorías de "fotos" de inventario en cualquier momento del tiempo.
    - **Descripción Dinámica**: Almacena glosas explicativas (ej: "Salida por entrega N° ENT-001") para facilitar el entendimiento humano de los reportes.

## ⚙️ Reglas de Negocio

- **Inmutabilidad de Registros**: Los asientos del Kardex son históricos y no deben ser editados manualmente. Cualquier corrección se realiza mediante un nuevo asiento de "Ajuste de Stock".
- **Filtro de Período**: Las consultas están optimizadas por Mes/Año para evitar sobrecargar el sistema con datos de años anteriores innecesarios.

## 📂 Esquema de Base de Datos Relacionada

- `kardex_producto`: Tabla histórica de movimientos.
- `lote_producto`: Referencia al lote específico del movimiento.
- `almacen`: Ubicación física del stock.
- `producto`: Bien afectado.
