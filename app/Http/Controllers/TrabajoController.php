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
                'subtrabajos_seleccionados' => 'sometimes|array',
                'cliente_nombre' => 'nullable|string|max:255',
                'cliente_telefono' => 'nullable|string|max:20'
            ]);

            $trabajo = Trabajo::create([
                'marca' => $validated['marca'],
                'modelo' => $validated['modelo'],
                'año' => $validated['año'],
                'trabajos' => $validated['trabajos'],
                'fecha_ingreso' => $validated['fecha_ingreso'],
                'color' => $validated['color'] ?? '#261472',
                'subtrabajos_estado' => [],
                'subtrabajos_seleccionados' => $validated['subtrabajos_seleccionados'] ?? [],
                'cliente_nombre' => $validated['cliente_nombre'] ?? null,
                'cliente_telefono' => $validated['cliente_telefono'] ?? null
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
            
            $estados = $trabajo->subtrabajos_estado ?? [];
            $usuarios = $trabajo->subtrabajos_usuario ?? [];
            
            if (!is_array($estados)) {
                $estados = [];
            }
            if (!is_array($usuarios)) {
                $usuarios = [];
            }
            
            $estados[$validated['subtrabajo']] = $validated['estado'];
            
            if ($validated['estado'] === true) {
                $usuarios[$validated['subtrabajo']] = $request->user()->name;
            }
            
            $trabajo->update([
                'subtrabajos_estado' => $estados,
                'subtrabajos_usuario' => $usuarios
            ]);

            $trabajo->refresh();

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

            $fechaTerminado = now();

            $usuariosUnicos = [];
            if ($trabajo->subtrabajos_usuario && is_array($trabajo->subtrabajos_usuario)) {
                $usuariosUnicos = array_values($trabajo->subtrabajos_usuario);
                $usuariosUnicos = array_filter($usuariosUnicos, function($usuario) {
                    return !empty($usuario) && is_string($usuario);
                });
                $usuariosUnicos = array_unique($usuariosUnicos);
            }
            
            $usuarioTermino = $request->user()->name;
            
            if (count($usuariosUnicos) > 1) {
                $usuariosTexto = implode(', ', $usuariosUnicos);
                $usuarioTermino = "{$request->user()->name} (Colaboraron: {$usuariosTexto})";
            } else if (count($usuariosUnicos) === 1) {
                $primerUsuario = reset($usuariosUnicos);
                if ($primerUsuario !== $request->user()->name) {
                    $usuarioTermino = "{$request->user()->name} (Realizado por: {$primerUsuario})";
                }
            }

            // Crear el historial y guardar el ID
            $historial = \App\Models\HistorialTrabajo::create([
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

            $trabajo->update(['completado' => true]);

            Log::info('Trabajo completado y guardado en historial exitosamente:', [
                'id' => $id,
                'historial_id' => $historial->id,
                'user_termino' => $request->user()->name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Trabajo completado exitosamente',
                'historial_id' => $historial->id  // ← DEVOLVEMOS EL ID DEL HISTORIAL
            ]);

        } catch (\Exception $e) {
            Log::error('Error en TrabajoController@destroy:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Error al completar trabajo: ' . $e->getMessage()
            ], 500);
        }
    }

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
                'subtrabajos_seleccionados' => 'sometimes|array',
                'cliente_nombre' => 'nullable|string|max:255',
                'cliente_telefono' => 'nullable|string|max:20'
            ]);

            $trabajo = Trabajo::findOrFail($id);
            $trabajo->update([
                'marca' => $validated['marca'],
                'modelo' => $validated['modelo'],
                'año' => $validated['año'],
                'trabajos' => $validated['trabajos'],
                'color' => $validated['color'] ?? $trabajo->color,
                'subtrabajos_seleccionados' => $validated['subtrabajos_seleccionados'] ?? $trabajo->subtrabajos_seleccionados,
                'cliente_nombre' => $validated['cliente_nombre'] ?? $trabajo->cliente_nombre,
                'cliente_telefono' => $validated['cliente_telefono'] ?? $trabajo->cliente_telefono
            ]);

            Log::info('Trabajo actualizado exitosamente:', ['id' => $id]);

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
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Trabajo no encontrado para actualizar:', ['id' => $id]);
            return response()->json([
                'success' => false,
                'error' => 'Trabajo no encontrado'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error en TrabajoController@update:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al actualizar trabajo',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function getLastUpdate()
    {
        try {
            $lastTrabajo = Trabajo::where('completado', false)->orderBy('updated_at', 'desc')->first();
            $trabajosCount = Trabajo::where('completado', false)->count();
            
            $stateHash = md5(
                ($lastTrabajo ? $lastTrabajo->updated_at->timestamp : '0') . 
                time() . 
                $trabajosCount
            );
            
            return response()->json([
                'success' => true,
                'last_update' => $lastTrabajo ? $lastTrabajo->updated_at->timestamp : time(),
                'state_hash' => $stateHash,
                'trabajos_count' => $trabajosCount,
                'current_time' => time(),
                'message' => 'Última actualización obtenida'
            ]);
        } catch (\Exception $e) {
            Log::error('Error en getLastUpdate:', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'error' => 'Error obteniendo última actualización',
                'last_update' => time(),
                'current_time' => time()
            ], 500);
        }
    }
}