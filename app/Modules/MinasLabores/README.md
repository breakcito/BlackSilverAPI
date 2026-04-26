# Módulo API: Minas y Labores (Deep Dive)

Gestiona la infraestructura física de la operación, los frentes de trabajo (labores) y su vinculación con las empresas contratistas.

## 🛠 Componentes del Módulo

### 1. Controladores (`MinasController`, `LaboresController`)

- **`crear_mina`**:
    - **Validación**: Valida nombre, ubicación y configuración de almacenes asociados.
- **`crear_labor`**:
    - **Validación**: Requiere `id_mina`, `id_empresa` (contratista), `id_tipo_labor` y parámetros técnicos (ancho, alto, nivel, sostenimiento).

### 2. Servicios de Infraestructura

#### `MinasService`

- Gestiona la creación de unidades mineras y la asignación de almacenes. Permite definir qué almacenes sirven a qué minas, lo cual es la base para el filtrado en los módulos de Requerimientos.

#### `LaboresService` (Gestión de Frentes)

- **`crear_labor`**:
    - **Correlativo Inteligente**: Genera un código de labor basado en la combinación de: `CÓDIGO_MINA` - `SIGLA_TIPO_LABOR` - `SEC_CORRELATIVO`. Por ejemplo: `UC-COR-001`.
    - **Parámetros Técnicos**: Registra las dimensiones físicas de la labor (ancho, alto) y el tipo de sostenimiento, datos cruciales para la planificación de seguridad y explosivos.
    - **Ciclo de Vida**: Gestiona el inicio y fin estimado de los frentes de trabajo.
- **`finalizar_labor`**:
    - Actualiza el estado a "Cerrado" y registra la fecha de cierre real, inhabilitando la labor para recibir nuevos requerimientos de materiales.

### 3. Capa de Datos (`LaboresData`, `MinasData`)

- **SQL de Consolidación**:
    - `get_historial_labores`: Consulta que une la labor con la empresa contratista y el tipo de labor, mostrando el estado operativo actual.
- **Correlativos**:
    - Implementa lógica de prefijos dinámicos según el tipo de labor seleccionada.

## ⚙️ Reglas de Negocio

- **Centro de Costos**: Cada labor actúa como un centro de costos en el sistema. Todo material despachado debe estar imputado a una labor activa.
- **Vinculación Contratista**: Una labor siempre pertenece a una empresa específica, permitiendo segregar los consumos por contratista.

## 📂 Esquema de Base de Datos Relacionada

- `mina`: Unidad operativa principal.
- `labor_mina`: Frente de trabajo específico.
- `tipo_labor`: Catálogo de tipos (Cruceros, Galerías, Chimeneas, etc.).
- `empresa`: Contratista responsable de la labor.
