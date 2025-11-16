<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        try {
            // Validar los datos de entrada
            $request->validate([
                'email' => 'required|email',
                'password' => 'required|min:1'
            ]);

            Log::info('Intento de login recibido:', [
                'email' => $request->email,
                'ip' => $request->ip()
            ]);

            // Buscar usuario incluyendo la relación role
            $user = User::where('email', $request->email)
                        ->with('role')
                        ->first();

            if (!$user) {
                Log::warning('Usuario no encontrado:', ['email' => $request->email]);
                return response()->json([
                    'error' => 'Las credenciales proporcionadas no son correctas.'
                ], 401);
            }

            Log::info('Usuario encontrado:', [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role ? $user->role->name : 'Sin rol'
            ]);

            // Verificar contraseña manualmente
            if (!Hash::check($request->password, $user->password)) {
                Log::warning('Contraseña incorrecta para usuario:', [
                    'email' => $request->email,
                    'user_id' => $user->id
                ]);
                return response()->json([
                    'error' => 'Las credenciales proporcionadas no son correctas.'
                ], 401);
            }

            Log::info('Credenciales válidas, generando token para usuario:', [
                'user_id' => $user->id,
                'name' => $user->name
            ]);

            // Crear token de Sanctum
            $token = $user->createToken('auth-token', ['*'], now()->addDays(30))->plainTextToken;

            // Respuesta exitosa
            return response()->json([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->name ?? 'tecnico'
                ],
                'token' => $token,
                'message' => 'Login exitoso'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Error de validación en login:', [
                'errors' => $e->errors(),
                'email' => $request->email
            ]);
            return response()->json([
                'error' => 'Datos de entrada inválidos.',
                'details' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Error interno en login:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'email' => $request->email
            ]);

            return response()->json([
                'error' => 'Error interno del servidor. Por favor, intenta nuevamente.'
            ], 500);
        }
    }

    public function user(Request $request)
    {
    try {
        $user = $request->user();
        
        if (!$user) {
            \Log::warning('Usuario no autenticado en endpoint /user');
            return response()->json(['error' => 'No autenticado'], 401);
        }

        $user->load('role');

        \Log::info('Usuario obtenido exitosamente:', [
            'user_id' => $user->id,
            'name' => $user->name
        ]);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->name
            ]
        ]);

    } catch (\Exception $e) {
        \Log::error('Error en endpoint /user:', [
            'message' => $e->getMessage(),
            'user_id' => $request->user()->id ?? 'unknown'
        ]);

        return response()->json(['error' => 'Error interno'], 500);
    }
    }

    public function logout(Request $request)
    {
        try {
            Log::info('Logout solicitado para usuario:', [
                'user_id' => $request->user()->id,
                'name' => $request->user()->name
            ]);

            // Revocar el token actual
            $request->user()->currentAccessToken()->delete();

            Log::info('Logout exitoso para usuario:', [
                'user_id' => $request->user()->id
            ]);

            return response()->json([
                'message' => 'Sesión cerrada exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error en logout:', [
                'message' => $e->getMessage(),
                'user_id' => $request->user()->id ?? 'unknown'
            ]);

            return response()->json([
                'error' => 'Error al cerrar sesión'
            ], 500);
        }
    }
    /**
     * Verificar estado de la autenticación
     */
    public function checkAuth(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json(['authenticated' => false], 401);
            }

            $user->load('role');

            return response()->json([
                'authenticated' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->name
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error verificando autenticación:', [
                'message' => $e->getMessage()
            ]);

            return response()->json(['authenticated' => false], 401);
        }
    }
}