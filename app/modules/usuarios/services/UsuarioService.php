<?php

namespace App\Modules\Usuarios\Services;

use App\Modules\Empresa\Infraestructure\Models\CargoEmpresa;
use App\Modules\Empresa\Infraestructure\Models\Empleado;
use App\Modules\Roles\Infraestructure\Models\Rol;
use App\Modules\Usuarios\Infraestructure\Models\Usuario;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

/**
 * Servicio para gestión de usuarios.
 */
class UsuarioService
{
    /**
     * Registrar un nuevo usuario con su empleado.
     *
     * @return array{success: bool, data: mixed, message: string|null}
     */
    public function registrarUsuario(
        int $idCargoEmpresa,
        int $idRol,
        string $nombre,
        string $apellido,
        ?string $dni,
        ?string $ruc,
        ?string $carnetExtranjeria,
        ?string $pasaporte,
        ?string $fechaNacimiento,
        string $username,
        string $password,
        ?UploadedFile $foto = null
    ): array {
        try {
            $cargoEmpresa = CargoEmpresa::buscarPorId($idCargoEmpresa);

            if (! $cargoEmpresa) {
                return ApiResponse::array(false, null, 'El cargo empresa especificado no existe');
            }

            $rol = Rol::buscarPorId($idRol);

            if (! $rol) {
                return ApiResponse::array(false, null, 'El rol especificado no existe');
            }

            $empleadoExistente = Empleado::buscarPorDocumento($dni, $ruc, $carnetExtranjeria, $pasaporte);

            if ($empleadoExistente) {
                return ApiResponse::array(false, null, 'Ya existe un empleado con alguno de los documentos proporcionados');
            }

            $usuarioExistente = Usuario::buscarPorUsername($username);

            if ($usuarioExistente) {
                return ApiResponse::array(false, null, 'El nombre de usuario ya está en uso');
            }

            $pathFoto = null;

            if ($foto) {
                $pathFoto = $foto->store('fotos_empleados', 'public');
            }

            $empleado = Empleado::crearEmpleado(
                $idCargoEmpresa,
                $nombre,
                $apellido,
                $dni,
                $ruc,
                $carnetExtranjeria,
                $pasaporte,
                $fechaNacimiento
            );

            if (! $empleado) {
                if ($pathFoto) {
                    Storage::disk('public')->delete($pathFoto);
                }

                return ApiResponse::array(false, null, 'Error al crear el empleado');
            }

            $usuario = Usuario::crearUsuario(
                $idRol,
                $empleado->id,
                $username,
                Hash::make($password)
            );

            if (! $usuario) {
                $empleado->delete();

                if ($pathFoto) {
                    Storage::disk('public')->delete($pathFoto);
                }

                return ApiResponse::array(false, null, 'Error al crear el usuario');
            }

            return ApiResponse::array(true, [
                'usuario' => $usuario,
                'empleado' => $empleado,
                'foto' => $pathFoto,
            ], 'Usuario registrado exitosamente');
        } catch (\Exception $e) {
            return ApiResponse::array(false, null, 'Error: '.$e->getMessage());
        }
    }

    /**
     * Iniciar sesión y generar JWT.
     *
     * @return array{success: bool, data: mixed, message: string|null}
     */
    public function login(string $username, string $password): array
    {
        try {
            $usuario = Usuario::buscarPorUsername($username);

            if (! $usuario) {
                return ApiResponse::array(false, null, 'Credenciales inválidas');
            }

            if (! Hash::check($password, $usuario->password)) {
                return ApiResponse::array(false, null, 'Credenciales inválidas');
            }

            $token = JWTAuth::fromUser($usuario);

            return ApiResponse::array(true, [
                'token' => $token,
                'token_type' => 'Bearer',
                'usuario' => $usuario,
            ], 'Inicio de sesión exitoso');
        } catch (\Exception $e) {
            return ApiResponse::array(false, null, 'Error: '.$e->getMessage());
        }
    }

    /**
     * Validar que un usuario existe y está activo.
     *
     * @return array{success: bool, data: mixed, message: string|null}
     */
    public function validarUsuarioActivo(int $idUsuario): array
    {
        try {
            $usuario = Usuario::buscarPorId($idUsuario);

            if (! $usuario) {
                return ApiResponse::array(false, null, 'Usuario no encontrado');
            }

            $usuario->load('empleado');

            if ($usuario->empleado && $usuario->empleado->estado !== 'Activo') {
                return ApiResponse::array(false, null, 'El empleado asociado no está activo');
            }

            return ApiResponse::array(true, $usuario, 'Usuario válido');
        } catch (\Exception $e) {
            return ApiResponse::array(false, null, 'Error: '.$e->getMessage());
        }
    }

    /**
     * Obtener lista de usuarios.
     *
     * @return array{success: bool, data: mixed, message: string|null}
     */
    public function obtenerUsuarios(): array
    {
        try {
            $usuarios = Usuario::with(['empleado', 'rol'])->get();

            return ApiResponse::array(true, $usuarios, null);
        } catch (\Exception $e) {
            return ApiResponse::array(false, null, 'Error: '.$e->getMessage());
        }
    }
}
