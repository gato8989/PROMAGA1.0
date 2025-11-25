<?php
// app/Http/Controllers/TrabajoController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Trabajo;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TrabajoController extends Controller
{
    public function index()
    {
        try {
            $trabajos = Trabajo::where('completado', false)->get();
            
            return response()->json([
                'success' => true,
                'data' => $trabajos
            ]);

        } catch (\Exception $e) {
            Log::error('Error en TrabajoController@index:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al cargar trabajos',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            Log::info('Creando nuevo trabajo:', $request->all());

            $validated = $request->validate([
                'marca' => 'required|string|max:255',
                'modelo' => 'required|string|max:255',
                'año' => 'required|string|max:10',
                'trabajos' => 'required|array',
                'trabajos.*' => 'string|max:255',
                'fecha_ingreso' => 'required|string|max:255',
                'color' => 'sometimes|string|max:255',
                'subtrabajos_seleccionados' => 'sometimes|array' // NUEVA VALIDACIÓN
            ]);

            $trabajo = Trabajo::create([
                'marca' => $validated['marca'],
                'modelo' => $validated['modelo'],
                'año' => $validated['año'],
                'trabajos' => $validated['trabajos'],
                'fecha_ingreso' => $validated['fecha_ingreso'],
                'color' => $validated['color'] ?? '#261472',
                'subtrabajos_estado' => [], // Initialize as empty array
                'subtrabajos_seleccionados' => $validated['subtrabajos_seleccionados'] ?? [] // NUEVO CAMPO
            ]);

            Log::info('Trabajo creado exitosamente:', ['id' => $trabajo->id]);

            return response()->json([
                'success' => true,
                'data' => $trabajo,
                'message' => 'Trabajo creado exitosamente'
            ], 201);

        } catch (ValidationException $e) {
            Log::error('Error de validación en TrabajoController@store:', [
                'errors' => $e->errors()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
            
        } catch (\Exception $e) {
            Log::error('Error crítico en TrabajoController@store:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al crear trabajo',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function updateSubtrabajo(Request $request, $id)
    {
        try {
            Log::info('Actualizando subtrabajo:', [
                'trabajo_id' => $id,
                'data' => $request->all(),
                'usuario_actual' => $request->user()->name,
                'usuario_id' => $request->user()->id
            ]);

            $validated = $request->validate([
                'subtrabajo' => 'required|string|max:255',
                'estado' => 'required|boolean'
            ]);

            $trabajo = Trabajo::findOrFail($id);
            
            // DEBUG: Verificar el estado actual
            Log::info('Estado actual del trabajo:', [
                'subtrabajos_estado' => $trabajo->subtrabajos_estado,
                'subtrabajos_usuario' => $trabajo->subtrabajos_usuario,
                'tipo_subtrabajos_usuario' => gettype($trabajo->subtrabajos_usuario)
            ]);
            
            // Inicializar arrays si son null
            $estados = $trabajo->subtrabajos_estado ?? [];
            $usuarios = $trabajo->subtrabajos_usuario ?? [];
            
            // Asegurarse de que son arrays
            if (!is_array($estados)) {
                $estados = [];
            }
            if (!is_array($usuarios)) {
                $usuarios = [];
            }
            
            // Actualizar estado del subtrabajo
            $estados[$validated['subtrabajo']] = $validated['estado'];
            
            // Registrar qué usuario hizo el cambio (solo cuando se marca como completado - verde)
            if ($validated['estado'] === true) {
                $usuarios[$validated['subtrabajo']] = $request->user()->name;
                Log::info('Registrando usuario para subtrabajo:', [
                    'subtrabajo' => $validated['subtrabajo'],
                    'usuario' => $request->user()->name,
                    'usuarios_actuales' => $usuarios
                ]);
            }
            
            $trabajo->update([
                'subtrabajos_estado' => $estados,
                'subtrabajos_usuario' => $usuarios
            ]);

            // Recargar el modelo para ver los cambios
            $trabajo->refresh();

            Log::info('Subtrabajo actualizado exitosamente:', [
                'trabajo_id' => $id,
                'subtrabajo' => $validated['subtrabajo'],
                'estado' => $validated['estado'],
                'usuario_registrado' => $validated['estado'] ? $request->user()->name : 'N/A',
                'todos_los_usuarios' => $trabajo->subtrabajos_usuario,
                'tipo_todos_los_usuarios' => gettype($trabajo->subtrabajos_usuario)
            ]);

            return response()->json([
                'success' => true,
                'data' => $trabajo,
                'message' => 'Subtrabajo actualizado exitosamente'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Trabajo no encontrado:', ['id' => $id]);
            return response()->json([
                'success' => false,
                'error' => 'Trabajo no encontrado'
            ], 404);
        } catch (ValidationException $e) {
            Log::error('Error de validación en TrabajoController@updateSubtrabajo:', [
                'errors' => $e->errors()
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error en TrabajoController@updateSubtrabajo:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Error al actualizar subtrabajo',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    
    public function updateNotas(Request $request, $id)
    {
        try {
            Log::info('Actualizando notas del trabajo:', ['id' => $id]);

            $trabajo = Trabajo::findOrFail($id);
            
            $validated = $request->validate([
                'notas' => 'nullable|string|max:1000'
            ]);

            $trabajo->update(['notas' => $validated['notas']]);

            Log::info('Notas actualizadas exitosamente:', [
                'id' => $id,
                'notas_length' => strlen($validated['notas'] ?? '')
            ]);

            return response()->json([
                'success' => true,
                'data' => $trabajo,
                'message' => 'Notas guardadas exitosamente'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Trabajo no encontrado para actualizar notas:', ['id' => $id]);
            
            return response()->json([
                'success' => false,
                'error' => 'Trabajo no encontrado'
            ], 404);
            
        } catch (\Exception $e) {
            Log::error('Error actualizando notas:', [
                'id' => $id,
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al guardar notas'
            ], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            Log::info('Completando trabajo y moviendo a historial:', [
                'id' => $id, 
                'user' => $request->user()->name
            ]);

            $trabajo = Trabajo::findOrFail($id);

            // Obtener la fecha y hora actual
            $fechaTerminado = now();

            // Obtener TODOS los usuarios únicos que interactuaron con los subtrabajos
            $usuariosUnicos = [];
            if ($trabajo->subtrabajos_usuario && is_array($trabajo->subtrabajos_usuario)) {
                $usuariosUnicos = array_values($trabajo->subtrabajos_usuario);
                // Filtrar valores nulos o vacíos y obtener únicos
                $usuariosUnicos = array_filter($usuariosUnicos, function($usuario) {
                    return !empty($usuario) && is_string($usuario);
                });
                $usuariosUnicos = array_unique($usuariosUnicos);
            }
            
            Log::info('Usuarios encontrados en subtrabajos:', [
                'usuarios' => $usuariosUnicos,
                'total' => count($usuariosUnicos),
                'subtrabajos_usuario' => $trabajo->subtrabajos_usuario
            ]);

            // Determinar qué mostrar en "Terminado por"
            $usuarioTermino = $request->user()->name; // Por defecto, el usuario que termina
            
            // Si hay múltiples usuarios únicos, agregarlos al usuario que termina
            if (count($usuariosUnicos) > 1) {
                $usuariosTexto = implode(', ', $usuariosUnicos);
                $usuarioTermino = "{$request->user()->name} (Colaboraron: {$usuariosTexto})";
            } else if (count($usuariosUnicos) === 1) {
                // Si solo hay un usuario y es diferente al que termina, mostrarlo
                $primerUsuario = reset($usuariosUnicos);
                if ($primerUsuario !== $request->user()->name) {
                    $usuarioTermino = "{$request->user()->name} (Inició: {$primerUsuario})";
                }
            }

            // Crear registro en historial
            \App\Models\HistorialTrabajo::create([
                'marca' => $trabajo->marca,
                'modelo' => $trabajo->modelo,
                'año' => $trabajo->año,
                'trabajos' => $trabajo->trabajos,
                'subtrabajos_estado' => $trabajo->subtrabajos_estado,
                'subtrabajos_usuario' => $trabajo->subtrabajos_usuario,
                'subtrabajos_seleccionados' => $trabajo->subtrabajos_seleccionados,
                'fecha_ingreso' => $trabajo->fecha_ingreso,
                'fecha_terminado' => $fechaTerminado->format('d/m/Y'),
                'hora_terminado' => $fechaTerminado->format('H:i:s'),
                'hora_creacion' => $trabajo->created_at ? $trabajo->created_at->format('H:i:s') : '00:00:00',
                'usuario_termino' => $usuarioTermino,
                'color' => $trabajo->color,
                'notas' => $trabajo->notas
            ]);

            // Marcar como completado en la tabla de trabajos
            $trabajo->update(['completado' => true]);

            Log::info('Trabajo completado y guardado en historial exitosamente:', [
                'id' => $id,
                'user_termino' => $request->user()->name,
                'usuarios_colaboradores' => $usuariosUnicos,
                'usuario_termino_final' => $usuarioTermino,
                'hora_terminado' => $fechaTerminado->format('H:i:s')
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Trabajo completado exitosamente'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Trabajo no encontrado para completar:', ['id' => $id]);
            return response()->json([
                'success' => false,
                'error' => 'Trabajo no encontrado'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error en TrabajoController@destroy:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Error al completar trabajo: '. $e->getMessage(),
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

        /**
     * Actualizar trabajo existente
     */
    public function update(Request $request, $id)
    {
        try {
            Log::info('Actualizando trabajo:', ['id' => $id, 'data' => $request->all()]);

            $validated = $request->validate([
                'marca' => 'required|string|max:255',
                'modelo' => 'required|string|max:255',
                'año' => 'required|string|max:10',
                'trabajos' => 'required|array',
                'trabajos.*' => 'string|max:255',
                'color' => 'sometimes|string|max:255',
                'subtrabajos_seleccionados' => 'sometimes|array' // NUEVA VALIDACIÓN
            ]);

            $trabajo = Trabajo::findOrFail($id);
            $trabajo->update([
                'marca' => $validated['marca'],
                'modelo' => $validated['modelo'],
                'año' => $validated['año'],
                'trabajos' => $validated['trabajos'],
                'color' => $validated['color'] ?? $trabajo->color,
                'subtrabajos_seleccionados' => $validated['subtrabajos_seleccionados'] ?? $trabajo->subtrabajos_seleccionados // NUEVO CAMPO
            ]);

            Log::info('Trabajo actualizado exitosamente:', [
                'id' => $id,
                'subtrabajos_actualizados' => isset($validated['subtrabajos_seleccionados'])
            ]);

            return response()->json([
                'success' => true,
                'data' => $trabajo,
                'message' => 'Trabajo actualizado exitosamente'
            ]);

        } catch (ValidationException $e) {
            Log::error('Error de validación en TrabajoController@update:', [
                'errors' => $e->errors()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
            
        } catch (\Exception $e) {
            Log::error('Error en TrabajoController@update:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al actualizar trabajo'
            ], 500);
        }
    }

    /**
     * Obtener última actualización de trabajos - MÉTODO NUEVO
     */
    public function getLastUpdate()
    {
        try {
            // Obtener el timestamp de la última modificación en la tabla trabajos
            $lastTrabajo = Trabajo::orderBy('updated_at', 'desc')->first();
            
            // También considerar si se han agregado o eliminado trabajos
            $latestCreated = Trabajo::orderBy('created_at', 'desc')->first();
            $trabajosCount = Trabajo::count();
            
            // Crear un hash único basado en el estado actual de los trabajos
            $stateHash = md5(
                ($lastTrabajo ? $lastTrabajo->updated_at->timestamp : '0') . 
                ($latestCreated ? $latestCreated->created_at->timestamp : '0') . 
                $trabajosCount
            );
            
            return response()->json([
                'success' => true,
                'last_update' => $lastTrabajo ? $lastTrabajo->updated_at->timestamp : time(),
                'state_hash' => $stateHash, // Hash del estado actual
                'trabajos_count' => $trabajosCount,
                'current_time' => time(),
                'message' => 'Última actualización obtenida',
                'debug' => [
                    'last_trabajo_id' => $lastTrabajo ? $lastTrabajo->id : null,
                    'last_updated_at' => $lastTrabajo ? $lastTrabajo->updated_at->toDateTimeString() : null,
                    'trabajos_count' => $trabajosCount
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Error en getLastUpdate:', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'error' => 'Error obteniendo última actualización',
                'last_update' => time(),
                'current_time' => time()
            ], 500);
        }
    }
}