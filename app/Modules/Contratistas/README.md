# Módulo API: Contratistas (Mineros)

Gestiona al personal operativo (mineros) que trabaja directamente en las unidades mineras y frentes de trabajo (labores).

## 🛠 Componentes del Módulo

### 1. Controlador (`ContratistasController`)

- **`crear_contratista`**: Registra personal operativo asociado a una **Mina** inicial y múltiples **Labores**.
- **`asignar_labores`**: Permite la rotación o actualización de frentes de trabajo para el personal minero.

### 2. Servicio de Contratistas (`ContratistasService`)

- **Gestión Operativa**: Los contratistas se anclan directamente a una Mina (`id_mina`).
- **Asignación de Labores**: Maneja la relación muchos-a-muchos con las labores de la mina asignada.
- **Transaccionalidad**: El registro y la actualización de labores se ejecutan dentro de transacciones de base de datos para garantizar consistencia.

### 3. Capa de Datos (`ContratistasData`)

- **SQL de Consolidación**:
    - `get_contratistas`: Retorna el perfil del minero junto con una cadena concatenada de sus labores actuales (`GROUP_CONCAT`).
- **Relaciones Muchos-a-Muchos**:
    - `asignar_labor`: Maneja la tabla pivot `labor_contratista`.

## ⚙️ Reglas de Negocio

- **Exclusividad Geográfica**: Un contratista solo puede tener asignadas labores que pertenezcan a su mina actual.
- **Sin Cuentas de Acceso**: Por regla general, los contratistas no poseen cuentas de usuario en el sistema; su gestión es realizada por administradores o responsables de mina.

## 📂 Esquema de Base de Datos Relacionada

- `contratista`: Maestro de personal minero.
- `labor_contratista`: Tabla pivot para asignación de frentes de trabajo.
- `mina`: Unidad minera de operación.
- `labor`: Frentes de trabajo específicos.
