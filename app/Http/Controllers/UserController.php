<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index()
    {
        try {
            Log::info('📋 Solicitando lista de usuarios', [
                'user_id' => auth()->check() ? auth()->user()->id : 'none',
                'ip' => request()->ip()
            ]);

            // Verificar autenticación manualmente
            if (!auth()->check()) {
                Log::warning('❌ Usuario no autenticado intentando acceder a usuarios');
                return response()->json(['error' => 'No autenticado'], 401);
            }

            $users = User::with('role')->get();

            Log::info('✅ Usuarios cargados exitosamente', ['count' => $users->count()]);

            return response()->json($users);

        } catch (\Exception $e) {
            Log::error('❌ Error crítico en UserController@index:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'user' => auth()->check() ? auth()->user()->id : 'none'
            ]);

            return response()->json([
                'error' => 'Error interno del servidor',
                'debug' => config('app.debug') ? $e->getMessage() : 'Contacte al administrador'
            ], 500);
        }
    }

    public function show(User $user)
    {
        try {
            if (!auth()->check()) {
                return response()->json(['error' => 'No autenticado'], 401);
            }

            $user->load('role');
            return response()->json($user);

        } catch (\Exception $e) {
            Log::error('Error en UserController@show:', [
                'message' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            return response()->json(['error' => 'Error interno'], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            Log::info('🆕 Iniciando creación de usuario', [
                'user_autenticado' => auth()->check() ? auth()->user()->id : 'none',
                'datos' => $request->except('password')
            ]);

            // Verificar autenticación y que sea admin
            if (!auth()->check()) {
                return response()->json(['error' => 'No autenticado'], 401);
            }

            $authUser = auth()->user();
            
            // Cargar la relación role si no está cargada
            if (!$authUser->relationLoaded('role')) {
                $authUser->load('role');
            }

            if (!$authUser->isAdmin()) {
                Log::warning('🚫 Usuario no admin intentando crear usuario', [
                    'user_id' => $authUser->id,
                    'role_id' => $authUser->role_id,
                    'isAdmin' => $authUser->isAdmin()
                ]);
                return response()->json(['error' => 'No autorizado. Se requiere rol de administrador.'], 403);
            }

            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users',
                'password' => 'required|min:6',
                'role_id' => 'required|exists:roles,id'
            ]);

            Log::info('✅ Datos validados para crear usuario');

            $user = User::create([
                'name' => $validatedData['name'],
                'email' => $validatedData['email'],
                'password' => Hash::make($validatedData['password']),
                'role_id' => $validatedData['role_id']
            ]);

            $user->load('role');
            
            Log::info('✅ Usuario creado exitosamente', [
                'nuevo_usuario_id' => $user->id,
                'role_asignado' => $user->role_id
            ]);

            return response()->json($user, 201);

        } catch (ValidationException $e) {
            Log::error('❌ Error de validación en UserController@store:', [
                'errors' => $e->errors()
            ]);
            return response()->json([
                'error' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
            
        } catch (\Exception $e) {
            Log::error('❌ Error crítico en UserController@store:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Error interno del servidor',
                'debug' => config('app.debug') ? $e->getMessage() : 'Contacte al administrador'
            ], 500);
        }
    }

    public function update(Request $request, User $user)
    {
        try {
            Log::info('🔄 INICIANDO ACTUALIZACIÓN DE USUARIO', [
                'usuario_a_actualizar' => $user->id,
                'usuario_autenticado' => auth()->check() ? auth()->user()->id : 'NO_AUTENTICADO',
                'datos_solicitud' => $request->except('password')
            ]);

            // Verificar autenticación
            if (!auth()->check()) {
                Log::warning('🚫 Usuario no autenticado intentando actualizar');
                return response()->json(['error' => 'No autenticado'], 401);
            }

            $authUser = auth()->user();
            
            // Cargar la relación role para el usuario autenticado
            if (!$authUser->relationLoaded('role')) {
                $authUser->load('role');
            }
            
            Log::info('👤 Usuario autenticado:', [
                'id' => $authUser->id,
                'name' => $authUser->name,
                'role_id' => $authUser->role_id,
                'role_name' => $authUser->role ? $authUser->role->name : 'null',
                'isAdmin' => $authUser->isAdmin()
            ]);

            // Verificar permisos de admin
            if (!$authUser->isAdmin()) {
                Log::warning('🚫 Usuario no admin intentando actualizar', [
                    'user_id' => $authUser->id,
                    'role_id' => $authUser->role_id,
                    'isAdmin' => $authUser->isAdmin()
                ]);
                return response()->json(['error' => 'No autorizado. Se requiere rol de administrador.'], 403);
            }

            // Validación mejorada
            $validatedData = $request->validate([
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|unique:users,email,' . $user->id,
                'password' => 'sometimes|min:6|nullable',
                'role_id' => 'sometimes|exists:roles,id'
            ]);

            Log::info('✅ Datos validados:', $validatedData);

            // Preparar datos para actualización
            $updateData = [];
            if ($request->has('name')) $updateData['name'] = $request->name;
            if ($request->has('email')) $updateData['email'] = $request->email;
            if ($request->has('role_id')) $updateData['role_id'] = $request->role_id;
            
            // Solo actualizar password si se proporciona y no está vacío
            if ($request->has('password') && !empty($request->password)) {
                $updateData['password'] = Hash::make($request->password);
                Log::info('🔐 Contraseña será actualizada');
            } else {
                Log::info('🔑 Contraseña no se actualizará (vacía o no proporcionada)');
            }

            Log::info('📝 Actualizando usuario con datos:', $updateData);

            // Actualizar usuario
            $user->update($updateData);
            
            // Recargar relaciones
            $user->load('role');

            Log::info('✅ USUARIO ACTUALIZADO EXITOSAMENTE', [
                'user_id' => $user->id,
                'nuevos_datos' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'role_id' => $user->role_id,
                    'role_name' => $user->role ? $user->role->name : 'null'
                ]
            ]);

            return response()->json([
                'message' => 'Usuario actualizado exitosamente',
                'user' => $user
            ]);

        } catch (ValidationException $e) {
            Log::error('❌ Error de validación en UserController@update:', [
                'errors' => $e->errors(),
                'user_id' => $user->id
            ]);
            return response()->json([
                'error' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
            
        } catch (\Exception $e) {
            Log::error('❌ ERROR CRÍTICO en UserController@update:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id
            ]);

            return response()->json([
                'error' => 'Error interno del servidor',
                'debug' => config('app.debug') ? $e->getMessage() : 'Contacte al administrador'
            ], 500);
        }
    }

    public function destroy(User $user)
    {
        try {
            Log::info('🗑️ Iniciando eliminación de usuario', [
                'usuario_a_eliminar' => $user->id,
                'usuario_autenticado' => auth()->check() ? auth()->user()->id : 'none'
            ]);

            if (!auth()->check()) {
                return response()->json(['error' => 'No autenticado'], 401);
            }

            $authUser = auth()->user();
            
            // Cargar relación role para verificar permisos
            if (!$authUser->relationLoaded('role')) {
                $authUser->load('role');
            }

            if (!$authUser->isAdmin()) {
                Log::warning('Usuario no admin intentando eliminar usuario', [
                    'user_id' => $authUser->id
                ]);
                return response()->json(['error' => 'No autorizado'], 403);
            }

            // Prevenir que un usuario se elimine a sí mismo
            if ($authUser->id === $user->id) {
                Log::warning('Usuario intentando eliminarse a sí mismo', [
                    'user_id' => $authUser->id
                ]);
                return response()->json(['error' => 'No puedes eliminar tu propio usuario'], 422);
            }

            $user->delete();
            
            Log::info('✅ Usuario eliminado exitosamente', ['user_id' => $user->id]);

            return response()->json([
                'message' => 'Usuario eliminado exitosamente'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error en UserController@destroy:', [
                'message' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            return response()->json(['error' => 'Error interno'], 500);
        }
    }

    public function roles()
    {
        try {
            if (!auth()->check()) {
                return response()->json(['error' => 'No autenticado'], 401);
            }

            $roles = Role::all();
            return response()->json($roles);

        } catch (\Exception $e) {
            Log::error('Error en UserController@roles:', [
                'message' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Error interno'], 500);
        }
    }
}