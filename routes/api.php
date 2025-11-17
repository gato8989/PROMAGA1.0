<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\TrabajoController;
use App\Http\Controllers\VehicleController;

use App\Http\Controllers\SSEController;

// Rutas SSE
Route::get('/sse/trabajos', [SSEController::class, 'streamTrabajos']);
Route::post('/sse/notify-change', [SSEController::class, 'notifyChange']);
Route::get('/sse/status', [SSEController::class, 'status']);


// Rutas para datos de vehículos
Route::get('/vehicles/makes', [VehicleController::class, 'getMakes']);
Route::get('/vehicles/years/{make}', [VehicleController::class, 'getYears']);
Route::get('/vehicles/models/{make}/{year}', [VehicleController::class, 'getModels']);
Route::get('/vehicles/search/{searchTerm}', [VehicleController::class, 'searchVehicles']);
Route::get('/vehicles/status', [VehicleController::class, 'getApiStatus']);
Route::post('/vehicles/refresh-cache', [VehicleController::class, 'refreshCache']);

// Rutas públicas
Route::post('/login', [AuthController::class, 'login']);

// Rutas protegidas con Sanctum - NO usar middleware 'auth'
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
            // Obtener el último updated_at de TODOS los trabajos activos (no completados)
            $lastUpdate = \App\Models\Trabajo::where('completado', false)->max('updated_at');
            
            // Si no hay trabajos activos, obtener de todos los trabajos
            if (!$lastUpdate) {
                $lastUpdate = \App\Models\Trabajo::max('updated_at');
            }
            
            // Si aún no hay trabajos, usar timestamp actual
            if (!$lastUpdate) {
                $lastUpdate = now();
            } else {
                // Convertir a Carbon si es string
                $lastUpdate = \Carbon\Carbon::parse($lastUpdate);
            }
            
            // También obtener conteo de trabajos activos para mayor precisión
            $trabajosCount = \App\Models\Trabajo::where('completado', false)->count();
            
            // Crear un hash más robusto que incluya timestamp y conteo
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
                'message' => 'Última actualización obtenida',
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
                'error' => 'Error obteniendo última actualización',
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

    // Historial routes - USANDO CLOSURES TEMPORALMENTE
    Route::get('/historial-trabajos', function (Request $request) {
        try {
            \Illuminate\Support\Facades\Log::info('Solicitando historial de trabajos via closure', ['filtros' => $request->all()]);

            $query = \App\Models\HistorialTrabajo::query();

            // Aplicar filtros
            if ($request->has('fecha') && $request->fecha) {
                $query->where('fecha_terminado', $request->fecha);
            }

            if ($request->has('marca') && $request->marca) {
                $query->where('marca', 'like', "%{$request->marca}%");
            }

            if ($request->has('modelo') && $request->modelo) {
                $query->where('modelo', 'like', "%{$request->modelo}%");
            }

            if ($request->has('busqueda') && $request->busqueda) {
                $query->where(function($q) use ($request) {
                    $q->where('marca', 'like', "%{$request->busqueda}%")
                      ->orWhere('modelo', 'like', "%{$request->busqueda}%")
                      ->orWhere('año', 'like', "%{$request->busqueda}%");
                });
            }

            if ($request->has('fecha_inicio') && $request->fecha_inicio && 
                $request->has('fecha_fin') && $request->fecha_fin) {
                $query->whereBetween('fecha_terminado', [$request->fecha_inicio, $request->fecha_fin]);
            }

            $trabajos = $query->orderBy('created_at', 'desc')->get();

            \Illuminate\Support\Facades\Log::info('Historial cargado exitosamente via closure', ['count' => $trabajos->count()]);

            return response()->json([
                'success' => true,
                'data' => $trabajos,
                'total' => $trabajos->count()
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

});

// Ruta de prueba
Route::get('/test', function () {
    return response()->json(['message' => 'API funcionando', 'timestamp' => now()]);
});