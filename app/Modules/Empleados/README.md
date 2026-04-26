# Módulo API: Empleados (Deep Dive)

Gestiona el capital humano de la empresa, su jerarquía organizativa y sus frentes de trabajo asignados.

## 🛠 Componentes del Módulo

### 1. Controlador (`EmpleadosController`)

- **`crear_empleado`**:
    - **Validación**: Valida documentos de identidad (DNI/RUC), cargos y áreas. Recibe la fotografía como un `UploadedFile`.
- **`actualizar_foto`**:
    - **Validación**: Valida que el archivo sea una imagen válida antes de procesar el almacenamiento.

### 2. Servicio de Personal (`EmpleadosService`)

- **`crear_empleado` (Transaccional)**:
    - **Validación Documental**: Verifica que el DNI no esté duplicado en el maestro para prevenir registros dobles.
    - **Gestión de Multimedia**: Utiliza el `ArchivoHelper` para persistir la foto en el almacenamiento físico y guarda la URL relativa en la BD.
    - **Control de Labores**:
        - Asocia al trabajador con múltiples labores operativas.
        - **Regla de Oro**: Valida que cada labor seleccionada pertenezca realmente a la Mina donde se está registrando al empleado.
    - **Consistencia**: Al finalizar, retorna el perfil completo con la URL de la foto transformada mediante la función `asset()`.

### 3. Capa de Datos (`EmpleadosData`)

- **SQL de Consolidación**:
    - `get_empleados`: Ejecuta una consulta que une las tablas de `cargo`, `area` y `mina`, trayendo en una sola petición el perfil completo del trabajador.
- **Relaciones Muchos-a-Muchos**:
    - `asignar_labor`: Maneja la tabla pivot `empleado_labor_mina`.

## ⚙️ Reglas de Negocio

- **Unicidad de Identidad**: El sistema bloquea el registro si detecta un documento de identidad ya existente.
- **Jerarquía Funcional**: Todo empleado debe estar anclado a un Cargo, y este cargo a un Área, garantizando el orden en el organigrama.
- **Restricción Geográfica**: Un empleado solo puede emitir requerimientos para las labores que tiene asignadas en su perfil.

## 📂 Esquema de Base de Datos Relacionada

- `empleado`: Maestro de personal.
- `empleado_labor_mina`: Tabla pivot de frentes de trabajo asignados.
- `cargo` / `area`: Estructura funcional.
- `mina`: Unidad minera de pertenencia.
