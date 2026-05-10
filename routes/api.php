<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\TrabajoController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\SSEController;
use App\Http\Controllers\DashboardController;


// Rutas para datos de vehÃ­culos
Route::get('/vehicles/makes', [VehicleController::class, 'getMakes']);
Route::get('/vehicles/years/{make}', [VehicleController::class, 'getYears']);
Route::get('/vehicles/models/{make}/{year}', [VehicleController::class, 'getModels']);
Route::get('/vehicles/search/{searchTerm}', [VehicleController::class, 'searchVehicles']);
Route::get('/vehicles/status', [VehicleController::class, 'getApiStatus']);
Route::post('/vehicles/refresh-cache', [VehicleController::class, 'refreshCache']);

// Rutas pÃºblicas
Route::post('/login', [AuthController::class, 'login']);

// Rutas protegidas con Sanctum 
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    
    // User management routes
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{user}', [UserController::class, 'show']);
    Route::get('/roles', [UserController::class, 'roles']);
    
    // Rutas que requieren ser admin
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);

    // Trabajos routes
    Route::get('/trabajos/last-update', function() {
        try {
            // Obtener el Ãºltimo updated_at de TODOS los trabajos activos (no completados)
            $lastUpdate = \App\Models\Trabajo::where('completado', false)->max('updated_at');
            
            // Si no hay trabajos activos, obtener de todos los trabajos
            if (!$lastUpdate) {
                $lastUpdate = \App\Models\Trabajo::max('updated_at');
            }
            
            // Si aÃºn no hay trabajos, usar timestamp actual
            if (!$lastUpdate) {
                $lastUpdate = now();
            } else {
                // Convertir a Carbon si es string
                $lastUpdate = \Carbon\Carbon::parse($lastUpdate);
            }
            
            // TambiÃ©n obtener conteo de trabajos activos para mayor precisiÃ³n
            $trabajosCount = \App\Models\Trabajo::where('completado', false)->count();
            
            // Crear un hash mÃ¡s robusto que incluya timestamp y conteo
            $stateHash = md5($lastUpdate->timestamp . '|' . $trabajosCount . '|' . $lastUpdate->format('Y-m-d H:i:s'));

            \Illuminate\Support\Facades\Log::info('State hash generado', [
                'last_update_timestamp' => $lastUpdate->timestamp,
                'trabajos_count' => $trabajosCount,
                'state_hash' => $stateHash,
                'last_updated_at' => $lastUpdate->toISOString()
            ]);

            return response()->json([
                'success' => true,
                'last_update' => $lastUpdate->timestamp,
                'state_hash' => $stateHash,
                'trabajos_count' => $trabajosCount,
                'last_updated_at' => $lastUpdate->toISOString(),
                'current_time' => now()->timestamp,
                'message' => 'Ãšltima actualizaciÃ³n obtenida',
                'debug' => [
                    'last_updated_at' => $lastUpdate->toISOString(),
                    'trabajos_count' => $trabajosCount,
                    'timestamp' => $lastUpdate->timestamp,
                    'hash_source' => $lastUpdate->timestamp . '|' . $trabajosCount . '|' . $lastUpdate->format('Y-m-d H:i:s')
                ]
            ]);
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error en last-update:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Error obteniendo Ãºltima actualizaciÃ³n',
                'last_update' => time(),
                'state_hash' => 'error_' . time()
            ], 500);
        }
    });

    Route::put('/trabajos/{id}', [TrabajoController::class, 'update']);
    Route::get('/trabajos', [TrabajoController::class, 'index']);
    Route::post('/trabajos', [TrabajoController::class, 'store']);
    Route::put('/trabajos/{trabajo}/subtrabajo', [TrabajoController::class, 'updateSubtrabajo']);
    Route::put('/trabajos/{trabajo}/notas', [TrabajoController::class, 'updateNotas']); 
    Route::delete('/trabajos/{id}', [TrabajoController::class, 'destroy']);

    // historial trabajos
    Route::get('/historial-trabajos', function (Request $request) {
        try {
            \Illuminate\Support\Facades\Log::info('Solicitando historial de trabajos via closure', [
                'filtros' => $request->all(),
                'page' => $request->page ?? 1,
                'per_page' => $request->per_page ?? 10
            ]);

            $query = \App\Models\HistorialTrabajo::query();

            // Aplicar filtros individuales
            if ($request->has('fecha') && $request->fecha) {
                $query->where('fecha_terminado', $request->fecha);
            }

            if ($request->has('marca') && $request->marca) {
                $query->where('marca', 'like', "%{$request->marca}%");
            }

            if ($request->has('modelo') && $request->modelo) {
                $query->where('modelo', 'like', "%{$request->modelo}%");
            }

            // FILTRO MEJORADO: BÃºsqueda inteligente
            if ($request->has('busqueda') && $request->busqueda) {
                $terminos = preg_split('/\s+/', trim($request->busqueda));
                
                $query->where(function($q) use ($terminos) {
                    foreach ($terminos as $termino) {
                        // Limpiar el tÃ©rmino de bÃºsqueda
                        $terminoLimpio = trim($termino);
                        if (empty($terminoLimpio)) continue;
                        
                        // Buscar en marca, modelo y aÃ±o
                        $q->where(function($subQuery) use ($terminoLimpio) {
                            $subQuery->where('marca', 'like', "%{$terminoLimpio}%")
                                    ->orWhere('modelo', 'like', "%{$terminoLimpio}%")
                                    ->orWhere('aÃ±o', 'like', "%{$terminoLimpio}%");
                        });
                    }
                });
            }

            // FILTRO CORREGIDO: Manejar fechas como strings en formato d/m/Y
            if ($request->has('fecha_inicio') && $request->fecha_inicio) {
                // Convertir fecha de YYYY-MM-DD a DD/MM/YYYY
                $fechaInicio = \Carbon\Carbon::parse($request->fecha_inicio)->format('d/m/Y');
                $query->whereRaw("STR_TO_DATE(fecha_terminado, '%d/%m/%Y') >= STR_TO_DATE(?, '%d/%m/%Y')", [$fechaInicio]);
            }

            if ($request->has('fecha_fin') && $request->fecha_fin) {
                // Convertir fecha de YYYY-MM-DD a DD/MM/YYYY
                $fechaFin = \Carbon\Carbon::parse($request->fecha_fin)->format('d/m/Y');
                $query->whereRaw("STR_TO_DATE(fecha_terminado, '%d/%m/%Y') <= STR_TO_DATE(?, '%d/%m/%Y')", [$fechaFin]);
            }

            // Obtener el total antes de paginar (para el frontend)
            $total = $query->count();
            
            // Aplicar paginaciÃ³n
            $page = $request->page ?? 1;
            $perPage = $request->per_page ?? 10;
            $offset = ($page - 1) * $perPage;
            
            $query->orderBy('created_at', 'desc')
                ->skip($offset)
                ->take($perPage);
                
            $trabajos = $query->get();

            \Illuminate\Support\Facades\Log::info('Historial cargado exitosamente via closure', [
                'count' => $trabajos->count(),
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'filtros_aplicados' => [
                    'busqueda_terminos' => $request->busqueda ? preg_split('/\s+/', trim($request->busqueda)) : [],
                    'fecha_inicio' => $request->fecha_inicio ? \Carbon\Carbon::parse($request->fecha_inicio)->format('d/m/Y') : null,
                    'fecha_fin' => $request->fecha_fin ? \Carbon\Carbon::parse($request->fecha_fin)->format('d/m/Y') : null,
                    'marca' => $request->marca,
                    'modelo' => $request->modelo
                ]
            ]);

            return response()->json([
                'success' => true,
                'data' => $trabajos,
                'total' => $total,
                'page' => (int)$page,
                'per_page' => (int)$perPage,
                'has_more' => ($offset + $trabajos->count()) < $total
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error en closure de historial:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al cargar historial: ' . $e->getMessage()
            ], 500);
        }
    });

    Route::get('/historial-filtros', function () {
        try {
            \Illuminate\Support\Facades\Log::info('Obteniendo filtros para historial via closure');

            $marcas = \App\Models\HistorialTrabajo::distinct()->pluck('marca')->filter()->values();
            $modelos = \App\Models\HistorialTrabajo::distinct()->pluck('modelo')->filter()->values();
            $fechas = \App\Models\HistorialTrabajo::distinct()->pluck('fecha_terminado')->filter()->values();

            \Illuminate\Support\Facades\Log::info('Filtros obtenidos exitosamente via closure', [
                'marcas_count' => $marcas->count(),
                'modelos_count' => $modelos->count(),
                'fechas_count' => $fechas->count()
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'marcas' => $marcas,
                    'modelos' => $modelos,
                    'fechas' => $fechas
                ]
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error en closure de filtros:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al cargar filtros: ' . $e->getMessage()
            ], 500);
        }
    });

    // NUEVA RUTA: Actualizar notas del historial
    Route::put('/historial-trabajos/{id}', function ($id, Request $request) {
        try {
            \Illuminate\Support\Facades\Log::info('Actualizando notas del historial:', [
                'id' => $id,
                'notas_length' => strlen($request->notas ?? '')
            ]);

            $trabajo = \App\Models\HistorialTrabajo::findOrFail($id);
            
            // Validar que solo se envÃ­en las notas
            $validated = $request->validate([
                'notas' => 'nullable|string|max:1000'
            ]);

            $trabajo->update(['notas' => $validated['notas']]);

            \Illuminate\Support\Facades\Log::info('Notas del historial actualizadas exitosamente:', [
                'id' => $id,
                'notas_length' => strlen($validated['notas'] ?? '')
            ]);

            return response()->json([
                'success' => true,
                'data' => $trabajo,
                'message' => 'Notas actualizadas exitosamente'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            \Illuminate\Support\Facades\Log::error('Trabajo no encontrado en historial para actualizar notas:', ['id' => $id]);
            
            return response()->json([
                'success' => false,
                'error' => 'Trabajo no encontrado en el historial'
            ], 404);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Illuminate\Support\Facades\Log::error('Error de validaciÃ³n al actualizar notas del historial:', [
                'id' => $id,
                'errors' => $e->errors()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error de validaciÃ³n',
                'errors' => $e->errors()
            ], 422);
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error actualizando notas del historial:', [
                'id' => $id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al actualizar notas: ' . $e->getMessage()
            ], 500);
        }
    });

    // Ruta alternativa PATCH para notas del historial
    Route::patch('/historial-trabajos/{id}', function ($id, Request $request) {
        try {
            \Illuminate\Support\Facades\Log::info('Actualizando notas del historial (PATCH):', [
                'id' => $id,
                'notas_length' => strlen($request->notas ?? '')
            ]);

            $trabajo = \App\Models\HistorialTrabajo::findOrFail($id);
            
            // Validar que solo se envÃ­en las notas
            $validated = $request->validate([
                'notas' => 'nullable|string|max:1000'
            ]);

            $trabajo->update(['notas' => $validated['notas']]);

            \Illuminate\Support\Facades\Log::info('Notas del historial actualizadas exitosamente (PATCH):', [
                'id' => $id,
                'notas_length' => strlen($validated['notas'] ?? '')
            ]);

            return response()->json([
                'success' => true,
                'data' => $trabajo,
                'message' => 'Notas actualizadas exitosamente'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            \Illuminate\Support\Facades\Log::error('Trabajo no encontrado en historial para actualizar notas (PATCH):', ['id' => $id]);
            
            return response()->json([
                'success' => false,
                'error' => 'Trabajo no encontrado en el historial'
            ], 404);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Illuminate\Support\Facades\Log::error('Error de validaciÃ³n al actualizar notas del historial (PATCH):', [
                'id' => $id,
                'errors' => $e->errors()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error de validaciÃ³n',
                'errors' => $e->errors()
            ], 422);
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error actualizando notas del historial (PATCH):', [
                'id' => $id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al actualizar notas: ' . $e->getMessage()
            ], 500);
        }
    });

    Route::delete('/historial-trabajos/{id}', function ($id) {
        try {
            \Illuminate\Support\Facades\Log::info('Eliminando trabajo del historial:', ['id' => $id]);

            $trabajo = \App\Models\HistorialTrabajo::findOrFail($id);
            $trabajo->delete();

            \Illuminate\Support\Facades\Log::info('Trabajo eliminado del historial exitosamente:', ['id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'Trabajo eliminado del historial exitosamente'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            \Illuminate\Support\Facades\Log::error('Trabajo no encontrado en historial:', ['id' => $id]);
            
            return response()->json([
                'success' => false,
                'error' => 'Trabajo no encontrado en el historial'
            ], 404);
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error eliminando trabajo del historial:', [
                'id' => $id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al eliminar trabajo del historial: ' . $e->getMessage()
            ], 500);
        }
    });



    // ==================== RUTAS DE CLIENTES ====================

    Route::post('/clientes/{cliente}/recomendacion', [App\Http\Controllers\ClienteController::class, 'enviarRecomendacion']);

    // Clientes CRUD
    Route::get('/clientes', [App\Http\Controllers\ClienteController::class, 'index']);
    Route::post('/clientes', [App\Http\Controllers\ClienteController::class, 'store']);
    Route::put('/clientes/{cliente}', [App\Http\Controllers\ClienteController::class, 'update']);
    Route::delete('/clientes/{cliente}', [App\Http\Controllers\ClienteController::class, 'destroy']);

    // WhatsApp
    Route::post('/clientes/{cliente}/recordatorio', [App\Http\Controllers\ClienteController::class, 'enviarRecordatorio']);
    Route::post('/clientes/{cliente}/finalizacion', [App\Http\Controllers\ClienteController::class, 'enviarFinalizacion']);
    Route::post('/clientes/{cliente}/garantia', [App\Http\Controllers\ClienteController::class, 'generarGarantia']);
    Route::get('/clientes/whatsapp-status', [App\Http\Controllers\ClienteController::class, 'whatsappStatus']);
    Route::post('/clientes/whatsapp-logout', [App\Http\Controllers\ClienteController::class, 'whatsappLogout']);

    // Búsqueda de trabajos del historial
    Route::get('/clientes/buscar-trabajos', [App\Http\Controllers\ClienteController::class, 'buscarTrabajosHistorial']);

    // ==================== RUTAS DASHBOARD ====================

    Route::get('/dashboard/technician-performance', [DashboardController::class, 'technicianPerformance']);
    
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

    Route::get('/dashboard/trend', [DashboardController::class, 'trend']);

    Route::get('/dashboard/brands', [DashboardController::class, 'brands']);

    Route::get('/dashboard/models', [DashboardController::class, 'models']);

    Route::get('/dashboard/years', [DashboardController::class, 'years']);

    Route::get('/dashboard/common-works', [DashboardController::class, 'commonWorks']);

    Route::get('/dashboard/work-times', [DashboardController::class, 'workTimes']);
    
    Route::get('/dashboard/hours-by-brand', [DashboardController::class, 'hoursByBrand']);

});

// Ruta de prueba
Route::get('/test', function () {
    return response()->json(['message' => 'API funcionando', 'timestamp' => now()]);
});
