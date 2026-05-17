# Módulo API: Kardex de Inventario (Deep Dive)

Provee la auditoría completa e inmutable de movimientos de inventario, permitiendo reconstruir la historia y trazabilidad de saldos de cada producto por almacén en tiempo real.

## 🛠 Componentes del Módulo

### 1. Controlador (`KardexController`)

- **`get_almacenes`**:
    - **Validación de Seguridad**: Filtra almacenes según privilegios. Si el usuario tiene el rol `almacenes_all`, puede auditar todas las sedes; de lo contrario, la API restringe la vista solo a los almacenes bajo su responsabilidad de cargo.
- **`get_resumen_kardex`**: Recupera el historial completo de movimientos filtrado por almacén, mes, y año.

### 2. Servicio de Auditoría (`KardexService`)

- Orquesta las consultas y reportes de inventario de manera aislada de los flujos de escritura.
- Garantiza la visibilidad controlada en base al estado global del **Modo Auditoría** (filtrando u ocultando registros marcados como confidenciales/sensibles en el ERP).

### 3. Capa de Datos Compartida (`App\Data\KardexProductosData`)

_Nota: Toda escritura del Kardex está centralizada a través del motor global de Lotes e Inventario, impidiendo modificaciones manuales directas del módulo para evitar corrupción de saldos._

- **`get_resumen_kardex` (Estructura Dual Lotes / Activos Fijos)**:
    - **Integración Híbrida**: Diseñado para soportar tanto bienes genéricos ordenados por **Lotes** como ítems únicos representados por **Activos Fijos**.
    - **Resolución de Producto**: Realiza `LEFT JOIN` a ambas entidades y resuelve el producto origen de forma condicional mediante una evaluación matemática e indexada:
      ```sql
      INNER JOIN producto p ON p.id = COALESCE(lp.id_producto, act.id_producto)
      ```
    - **Consistencia de Saldo**: Almacena `stock_anterior_base` y `stock_resultante_base` por cada fila del Kardex junto con la glosa descriptiva que vincula al documento físico origen.

---

## ⚙️ Reglas de Negocio

- **Inmutabilidad Absoluta**: Ningún registro en el Kardex se puede borrar o actualizar. Un error operativo se subsana mediante un nuevo documento de "Ajuste de Stock" de signo contrario.
- **Identificación en Grilla**: En el frontend, la columna "Lote / Activo" hereda de manera automática el código físico disponible: muestra `correlativo_lote` si es un insumo genérico consumible o `correlativo_activo_fijo` si se trata del ingreso/movimiento de un activo único.

---

## 📂 Esquema de Base de Datos Relacionada

- `kardex_producto`: Bitácora histórica inmutable de transacciones.
- `lote_producto`: Referencia del lote (para bienes consumibles).
- `activo_fijo`: Referencia del activo físico (para maquinarias o vehículos únicos).
- `producto`: Datos maestros y clasificación del bien afectado.
- `almacen`: Ubicación física donde ocurrió la transacción.
