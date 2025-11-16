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
                'data' => $request->all()
            ]);

            $validated = $request->validate([
                'subtrabajo' => 'required|string|max:255',
                'estado' => 'required|boolean'
            ]);

            $trabajo = Trabajo::findOrFail($id);
            $trabajo->actualizarSubtrabajoEstado(
                $validated['subtrabajo'], 
                $validated['estado']
            );

            Log::info('Subtrabajo actualizado exitosamente:', [
                'trabajo_id' => $id,
                'subtrabajo' => $validated['subtrabajo'],
                'estado' => $validated['estado']
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
            Log::info('Completando trabajo y moviendo a historial:', ['id' => $id, 'user' => $request->user()->name]);

            $trabajo = Trabajo::findOrFail($id);
            
            // Obtener la fecha y hora actual
            $fechaTerminado = now();
            
            // Crear registro en historial
            \App\Models\HistorialTrabajo::create([
                'marca' => $trabajo->marca,
                'modelo' => $trabajo->modelo,
                'año' => $trabajo->año,
                'trabajos' => $trabajo->trabajos,
                'subtrabajos_estado' => $trabajo->subtrabajos_estado,
                'subtrabajos_seleccionados' => $trabajo->subtrabajos_seleccionados,
                'fecha_ingreso' => $trabajo->fecha_ingreso,
                'fecha_terminado' => $fechaTerminado->format('d/m/Y'),
                'hora_terminado' => $fechaTerminado->format('H:i:s'), // NUEVO: Hora de terminado
                'hora_creacion' => $trabajo->created_at ? $trabajo->created_at->format('H:i:s') : '00:00:00', // NUEVO: Hora de creación
                'usuario_termino' => $request->user()->name,
                'color' => $trabajo->color,
                'notas' => $trabajo->notas
            ]);

            // Marcar como completado en la tabla de trabajos
            $trabajo->update(['completado' => true]);

            Log::info('Trabajo completado y guardado en historial exitosamente:', [
                'id' => $id,
                'user' => $request->user()->name,
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
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al completar trabajo: ' . $e->getMessage(),
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
            // Obtener el último updated_at de trabajos activos
            $lastUpdate = Trabajo::where('completado', false)
                ->max('updated_at');
            
            // Si no hay trabajos, usar timestamp actual
            $timestamp = $lastUpdate ? $lastUpdate->getTimestamp() : now()->timestamp;
            
            Log::info('Última actualización obtenida', ['timestamp' => $timestamp]);
            
            return response()->json([
                'success' => true,
                'last_update' => $timestamp,
                'current_time' => now()->timestamp,
                'message' => 'Última actualización obtenida'
            ]);

        } catch (\Exception $e) {
            Log::error('Error en getLastUpdate:', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'error' => 'Error al obtener última actualización',
                'last_update' => now()->timestamp // Fallback
            ], 500);
        }
    }
}