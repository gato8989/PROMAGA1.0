<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\HistorialTrabajo;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Obtener estadísticas generales
     */
    public function stats(Request $request)
    {
        try {
            $fechaInicio = $request->query('fecha_inicio')
                ? Carbon::parse($request->query('fecha_inicio'))->startOfDay()
                : now()->subDays(30)->startOfDay();
            $fechaFin = $request->query('fecha_fin')
                ? Carbon::parse($request->query('fecha_fin'))->endOfDay()
                : now()->endOfDay();
                
            \Illuminate\Support\Facades\Log::info('Dashboard Stats - Fechas:', [
                'inicio' => $fechaInicio->toDateTimeString(),
                'fin' => $fechaFin->toDateTimeString()
            ]);
            
            $trabajos = HistorialTrabajo::whereBetween('created_at', [$fechaInicio, $fechaFin])->get();
            
            \Illuminate\Support\Facades\Log::info('Dashboard Stats - Trabajos encontrados:', [
                'count' => $trabajos->count()
            ]);
            
            $totalVehiculos = $trabajos->count();
            $tiempoPromedio = 0;
            $trabajosRealizados = 0;
            
            if ($totalVehiculos > 0) {
                $tiempos = [];
                foreach ($trabajos as $trabajo) {
                    $entrada = $trabajo->created_at;
                    $salida = $trabajo->updated_at;
                    
                    if ($entrada && $salida && $entrada < $salida) {
                        $tiempos[] = $entrada->diffInMinutes($salida);
                    }
                    
                    if (is_array($trabajo->trabajos)) {
                        $trabajosRealizados += count($trabajo->trabajos);
                    }
                }
                $tiempoPromedio = count($tiempos) > 0 ? round(array_sum($tiempos) / count($tiempos)) : 0;
            }
            
            return response()->json([
                'success' => true, 
                'data' => [
                    'totalVehiculos' => $totalVehiculos, 
                    'tiempoPromedio' => $tiempoPromedio, 
                    'trabajosRealizados' => $trabajosRealizados, 
                    'fechaInicio' => $fechaInicio->format('Y-m-d'), 
                    'fechaFin' => $fechaFin->format('Y-m-d')
                ]
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error en stats:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false, 
                'error' => 'Error al cargar estadísticas: ' . $e->getMessage(),
                'data' => $this->getEmptyStatsData()
            ], 500);
        }
    }

    /**
     * Obtener tendencia diaria
     */
    public function trend(Request $request)
    {
        try {
            $fechaInicio = $request->query('fecha_inicio') 
                ? Carbon::parse($request->query('fecha_inicio'))->startOfDay() 
                : now()->subDays(30)->startOfDay();
            $fechaFin = $request->query('fecha_fin') 
                ? Carbon::parse($request->query('fecha_fin'))->endOfDay() 
                : now()->endOfDay();
                
            \Illuminate\Support\Facades\Log::info('Dashboard Trend - Fechas:', [
                'inicio' => $fechaInicio->toDateTimeString(),
                'fin' => $fechaFin->toDateTimeString()
            ]);
            
            $trabajos = HistorialTrabajo::whereBetween('created_at', [$fechaInicio, $fechaFin])->get();
            
            \Illuminate\Support\Facades\Log::info('Dashboard Trend - Trabajos encontrados:', [
                'count' => $trabajos->count()
            ]);
            
            $tendencia = [];
            $currentDate = $fechaInicio->copy();
            
            while ($currentDate <= $fechaFin) {
                $fechaStr = $currentDate->format('Y-m-d');
                
                $trabajosDelDia = $trabajos->filter(function($trabajo) use ($currentDate) {
                    return $trabajo->created_at->format('Y-m-d') === $currentDate->format('Y-m-d');
                });
                
                $vehiculosCount = $trabajosDelDia->count();
                $horas = 0;
                
                foreach ($trabajosDelDia as $trabajo) {
                    $minutos = $trabajo->created_at->diffInMinutes($trabajo->updated_at);
                    $horas += $minutos / 60;
                }
                
                $tendencia[] = [
                    'fecha' => $fechaStr,
                    'vehiculos' => $vehiculosCount,
                    'horas' => round($horas, 2)
                ];
                
                $currentDate->addDay();
            }
            
            return response()->json(['success' => true, 'data' => $tendencia]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error en trend:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false, 
                'error' => 'Error al cargar tendencias: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Obtener distribución por marcas
     */
    public function brands(Request $request)
    {
        try {
            $fechaInicio = $request->query('fecha_inicio') 
                ? Carbon::parse($request->query('fecha_inicio'))->startOfDay() 
                : now()->subDays(30)->startOfDay();
            $fechaFin = $request->query('fecha_fin') 
                ? Carbon::parse($request->query('fecha_fin'))->endOfDay() 
                : now()->endOfDay();
                
            $trabajos = HistorialTrabajo::whereBetween('created_at', [$fechaInicio, $fechaFin])->get();
            
            if ($trabajos->isEmpty()) {
                return response()->json([
                    'success' => true, 
                    'data' => [],
                    'message' => 'No hay datos de marcas disponibles'
                ]);
            }
            
            $marcas = $trabajos->groupBy('marca')->map(fn($g) => $g->count())->sortDesc();
            $total = $marcas->sum();
            $data = [];
            
            foreach ($marcas as $marca => $cantidad) {
                $porcentaje = $total > 0 ? round(($cantidad / $total) * 100, 2) : 0;
                $data[] = [
                    'marca' => $marca ?: 'Sin especificar', 
                    'cantidad' => $cantidad, 
                    'porcentaje' => $porcentaje
                ];
            }
            
            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error en brands:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false, 
                'error' => 'Error al cargar marcas: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Obtener modelos más comunes
     */
    public function models(Request $request)
    {
        try {
            $fechaInicio = $request->query('fecha_inicio') 
                ? Carbon::parse($request->query('fecha_inicio'))->startOfDay() 
                : now()->subDays(30)->startOfDay();
            $fechaFin = $request->query('fecha_fin') 
                ? Carbon::parse($request->query('fecha_fin'))->endOfDay() 
                : now()->endOfDay();
                
            $trabajos = HistorialTrabajo::whereBetween('created_at', [$fechaInicio, $fechaFin])->get();
            
            if ($trabajos->isEmpty()) {
                return response()->json([
                    'success' => true, 
                    'data' => [],
                    'message' => 'No hay datos de modelos disponibles'
                ]);
            }
            
            $modelos = $trabajos->groupBy('modelo')->map(fn($g) => $g->count())->sortDesc()->take(10);
            $data = [];
            
            foreach ($modelos as $modelo => $cantidad) {
                $data[] = [
                    'modelo' => $modelo ?: 'Sin especificar', 
                    'cantidad' => $cantidad
                ];
            }
            
            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error en models:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false, 
                'error' => 'Error al cargar modelos: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Obtener distribución por años
     */
    public function years(Request $request)
    {
        try {
            $fechaInicio = $request->query('fecha_inicio') 
                ? Carbon::parse($request->query('fecha_inicio'))->startOfDay() 
                : now()->subDays(30)->startOfDay();
            $fechaFin = $request->query('fecha_fin') 
                ? Carbon::parse($request->query('fecha_fin'))->endOfDay() 
                : now()->endOfDay();
                
            $trabajos = HistorialTrabajo::whereBetween('created_at', [$fechaInicio, $fechaFin])->get();
            
            if ($trabajos->isEmpty()) {
                return response()->json([
                    'success' => true, 
                    'data' => [],
                    'message' => 'No hay datos de años disponibles'
                ]);
            }
            
            $anos = $trabajos->groupBy('año')->map(fn($g) => $g->count())->sortKeys(SORT_NUMERIC, true);
            $data = [];
            
            foreach ($anos as $ano => $cantidad) {
                $data[] = [
                    'año' => $ano ?: 'Sin especificar', 
                    'cantidad' => $cantidad
                ];
            }
            
            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error en years:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false, 
                'error' => 'Error al cargar años: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Obtener trabajos más comunes
     */
    public function commonWorks(Request $request)
    {
        try {
            $fechaInicio = $request->query('fecha_inicio') 
                ? Carbon::parse($request->query('fecha_inicio'))->startOfDay() 
                : now()->subDays(30)->startOfDay();
            $fechaFin = $request->query('fecha_fin') 
                ? Carbon::parse($request->query('fecha_fin'))->endOfDay() 
                : now()->endOfDay();
                
            $trabajos = HistorialTrabajo::whereBetween('created_at', [$fechaInicio, $fechaFin])->get();
            
            if ($trabajos->isEmpty()) {
                return response()->json([
                    'success' => true, 
                    'data' => [],
                    'message' => 'No hay datos de trabajos disponibles'
                ]);
            }
            
            $trabajosConteo = [];
            foreach ($trabajos as $trabajo) {
                if (is_array($trabajo->trabajos)) {
                    foreach ($trabajo->trabajos as $work) {
                        $workClean = trim($work);
                        if (!empty($workClean)) {
                            // Limpiar el nombre del trabajo (mantener letras, números y espacios)
                            $workClean = preg_replace('/[^a-zA-Z0-9áéíóúñÑüÜ\s\-]/u', '', $workClean);
                            $workClean = trim($workClean);
                            if (!empty($workClean)) {
                                $trabajosConteo[$workClean] = ($trabajosConteo[$workClean] ?? 0) + 1;
                            }
                        }
                    }
                }
            }
            
            if (empty($trabajosConteo)) {
                return response()->json([
                    'success' => true, 
                    'data' => [],
                    'message' => 'No se encontraron trabajos registrados'
                ]);
            }
            
            arsort($trabajosConteo);
            $data = array_slice($trabajosConteo, 0, 10, true);
            $formattedData = array_map(
                fn($trabajo, $cantidad) => ['trabajo' => $trabajo, 'cantidad' => $cantidad], 
                array_keys($data), 
                $data
            );
            
            return response()->json(['success' => true, 'data' => $formattedData]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error en commonWorks:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false, 
                'error' => 'Error al cargar trabajos: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Obtener tiempos de trabajo - CORREGIDO (en horas y siempre muestra trabajos)
     */
    public function workTimes(Request $request)
    {
        try {
            $fechaInicio = $request->query('fecha_inicio') 
                ? Carbon::parse($request->query('fecha_inicio'))->startOfDay() 
                : now()->subDays(30)->startOfDay();
            $fechaFin = $request->query('fecha_fin') 
                ? Carbon::parse($request->query('fecha_fin'))->endOfDay() 
                : now()->endOfDay();
                
            \Illuminate\Support\Facades\Log::info('WorkTimes - Fechas:', [
                'inicio' => $fechaInicio->toDateTimeString(),
                'fin' => $fechaFin->toDateTimeString()
            ]);
            
            $trabajos = HistorialTrabajo::whereBetween('created_at', [$fechaInicio, $fechaFin])->get();
            
            \Illuminate\Support\Facades\Log::info('WorkTimes - Trabajos encontrados:', [
                'count' => $trabajos->count()
            ]);
            
            if ($trabajos->isEmpty()) {
                return response()->json([
                    'success' => true, 
                    'data' => [],
                    'message' => 'No hay datos de tiempos disponibles'
                ]);
            }
            
            $tiemposPorTrabajo = [];
            
            foreach ($trabajos as $trabajo) {
                $trabajosRealizados = $trabajo->trabajos;
                
                // Calcular horas (convertir minutos a horas)
                $minutos = $trabajo->created_at->diffInMinutes($trabajo->updated_at);
                $horas = $minutos / 60;
                
                // Redondear a 2 decimales
                $horas = round($horas, 2);
                
                \Illuminate\Support\Facades\Log::info('Procesando trabajo para workTimes:', [
                    'id' => $trabajo->id,
                    'marca' => $trabajo->marca,
                    'trabajos' => $trabajosRealizados,
                    'minutos' => $minutos,
                    'horas' => $horas,
                    'created_at' => $trabajo->created_at->toDateTimeString(),
                    'updated_at' => $trabajo->updated_at->toDateTimeString()
                ]);
                
                if (is_array($trabajosRealizados) && !empty($trabajosRealizados)) {
                    foreach ($trabajosRealizados as $work) {
                        // Limpiar el trabajo pero mantener letras, números y espacios
                        $workClean = trim($work);
                        
                        // Si está vacío, ignorar
                        if (empty($workClean)) {
                            continue;
                        }
                        
                        // Limpiar caracteres especiales pero mantener letras, números y espacios
                        $workClean = preg_replace('/[^a-zA-Z0-9áéíóúñÑüÜ\s\-]/u', '', $workClean);
                        $workClean = trim($workClean);
                        
                        if (!empty($workClean)) {
                            if (!isset($tiemposPorTrabajo[$workClean])) {
                                $tiemposPorTrabajo[$workClean] = [];
                            }
                            
                            // Solo agregar si hay tiempo (puede ser 0 o más)
                            // Incluir incluso tiempos de 0 horas para mostrar trabajos rápidos
                            $tiemposPorTrabajo[$workClean][] = $horas;
                        }
                    }
                }
            }
            
            \Illuminate\Support\Facades\Log::info('WorkTimes - Tiempos por trabajo (horas):', [
                'trabajos_procesados' => count($tiemposPorTrabajo),
                'detalle' => $tiemposPorTrabajo
            ]);
            
            if (empty($tiemposPorTrabajo)) {
                // Si no hay trabajos procesados, devolver algunos datos de ejemplo o vacío
                return response()->json([
                    'success' => true, 
                    'data' => [],
                    'message' => 'No hay datos de tiempos disponibles para los trabajos procesados'
                ]);
            }
            
            $data = [];
            foreach ($tiemposPorTrabajo as $trabajo => $tiempos) {
                if (!empty($tiempos)) {
                    $data[] = [
                        'trabajo' => $trabajo, 
                        'minimo' => round(min($tiempos), 2),      // En horas
                        'promedio' => round(array_sum($tiempos) / count($tiempos), 2), // En horas
                        'maximo' => round(max($tiempos), 2)      // En horas
                    ];
                }
            }
            
            // Ordenar por promedio de mayor a menor
            usort($data, fn($a, $b) => $b['promedio'] <=> $a['promedio']);
            
            // Siempre mostrar hasta 10 trabajos (si hay menos, mostrar los que existan)
            $result = array_slice($data, 0, 10);
            
            \Illuminate\Support\Facades\Log::info('WorkTimes - Resultado final (horas):', [
                'count' => count($result),
                'data' => $result
            ]);
            
            return response()->json(['success' => true, 'data' => $result]);
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error en workTimes:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false, 
                'error' => 'Error al cargar tiempos: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }


    /**
     * Obtener rendimiento de técnicos
     */
    public function technicianPerformance(Request $request)
    {
        try {
            $fechaInicio = $request->query('fecha_inicio') 
                ? Carbon::parse($request->query('fecha_inicio'))->startOfDay() 
                : now()->subDays(30)->startOfDay();
            $fechaFin = $request->query('fecha_fin') 
                ? Carbon::parse($request->query('fecha_fin'))->endOfDay() 
                : now()->endOfDay();
                
            \Illuminate\Support\Facades\Log::info('TechnicianPerformance - Fechas:', [
                'inicio' => $fechaInicio->toDateTimeString(),
                'fin' => $fechaFin->toDateTimeString()
            ]);
            
            // Obtener todos los trabajos en el rango de fechas
            $trabajos = HistorialTrabajo::whereBetween('created_at', [$fechaInicio, $fechaFin])->get();
            
            if ($trabajos->isEmpty()) {
                return response()->json([
                    'success' => true, 
                    'data' => [],
                    'message' => 'No hay datos de técnicos disponibles'
                ]);
            }
            
            // Array para almacenar datos por técnico
            $tecnicosData = [];
            
            foreach ($trabajos as $trabajo) {
                // Obtener el técnico que terminó el trabajo
                $tecnicoRaw = $trabajo->usuario_termino ?: 'Sin asignar';
                
                // Extraer todos los técnicos del string
                $tecnicos = $this->extractTechnicians($tecnicoRaw);
                
                // Calcular horas trabajadas para este trabajo
                $minutos = $trabajo->created_at->diffInMinutes($trabajo->updated_at);
                $horas = $minutos / 60;
                
                // Para cada técnico encontrado, sumar el trabajo y las horas
                foreach ($tecnicos as $tecnico) {
                    if (!isset($tecnicosData[$tecnico])) {
                        $tecnicosData[$tecnico] = [
                            'tecnico' => $tecnico,
                            'horas_trabajadas' => 0,
                            'vehiculos_trabajados' => 0,
                            'tiempos' => []
                        ];
                    }
                    
                    $tecnicosData[$tecnico]['horas_trabajadas'] += $horas;
                    $tecnicosData[$tecnico]['vehiculos_trabajados']++;
                    
                    if ($horas > 0) {
                        $tecnicosData[$tecnico]['tiempos'][] = $horas;
                    }
                }
            }
            
            // Calcular tiempo promedio y rendimiento para cada técnico
            $result = [];
            
            // Primero, obtener el máximo de trabajos para calcular el rendimiento relativo
            $maxTrabajos = 0;
            foreach ($tecnicosData as $tecnico) {
                $maxTrabajos = max($maxTrabajos, $tecnico['vehiculos_trabajados']);
            }
            
            foreach ($tecnicosData as $tecnico) {
                $promedio = 0;
                if (count($tecnico['tiempos']) > 0) {
                    $promedio = round(array_sum($tecnico['tiempos']) / count($tecnico['tiempos']), 2);
                }
                
                // Calcular rendimiento basado en número de trabajos y tiempo promedio
                $porcentajeTrabajos = $maxTrabajos > 0 ? ($tecnico['vehiculos_trabajados'] / $maxTrabajos) * 100 : 0;
                
                // Determinar nivel de rendimiento
                if ($porcentajeTrabajos >= 70 && $promedio <= 5) {
                    $rendimiento = 'Alto';
                    $rendimientoClass = 'rendimiento-alta';
                    $rendimientoIcon = '🚀';
                } elseif ($porcentajeTrabajos >= 40 && $promedio <= 10) {
                    $rendimiento = 'Medio';
                    $rendimientoClass = 'rendimiento-media';
                    $rendimientoIcon = '📈';
                } else {
                    $rendimiento = 'Bajo';
                    $rendimientoClass = 'rendimiento-baja';
                    $rendimientoIcon = '📉';
                }
                
                $result[] = [
                    'tecnico' => $tecnico['tecnico'],
                    'trabajos' => $tecnico['vehiculos_trabajados'],
                    'tiempo_promedio' => $promedio,
                    'horas_trabajadas' => round($tecnico['horas_trabajadas'], 2),
                    'rendimiento' => $rendimiento,
                    'rendimiento_class' => $rendimientoClass,
                    'rendimiento_icon' => $rendimientoIcon,
                    'porcentaje' => round($porcentajeTrabajos, 1)
                ];
            }
            
            // Ordenar por horas trabajadas (de mayor a menor)
            usort($result, fn($a, $b) => $b['horas_trabajadas'] <=> $a['horas_trabajadas']);
            
            \Illuminate\Support\Facades\Log::info('TechnicianPerformance - Resultado:', [
                'count' => count($result),
                'data' => $result
            ]);
            
            return response()->json(['success' => true, 'data' => $result]);
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error en technicianPerformance:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false, 
                'error' => 'Error al cargar rendimiento de técnicos: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Extraer todos los técnicos de un string
     * Ejemplo: "Técnico Ejemplo (Colaboraron: Administrador, Técnico Ejemplo)" 
     * Devuelve: ["Técnico Ejemplo", "Administrador"]
     */
    private function extractTechnicians($tecnicoString)
    {
        $tecnicos = [];
        
        // Buscar el patrón " (Colaboraron: X, Y, Z)"
        if (preg_match('/\(Colaboraron:\s*([^)]+)\)/i', $tecnicoString, $matches)) {
            // Extraer el técnico principal (antes del paréntesis)
            $tecnicoPrincipal = trim(preg_replace('/\s*\(Colaboraron:.*\)/i', '', $tecnicoString));
            if (!empty($tecnicoPrincipal)) {
                $tecnicos[] = $tecnicoPrincipal;
            }
            
            // Extraer los colaboradores
            $colaboradores = explode(',', $matches[1]);
            foreach ($colaboradores as $colaborador) {
                $colaborador = trim($colaborador);
                if (!empty($colaborador) && !in_array($colaborador, $tecnicos)) {
                    $tecnicos[] = $colaborador;
                }
            }
        } else {
            // Si no hay patrón de colaboradores, es un solo técnico
            $tecnicos[] = trim($tecnicoString);
        }
        
        // Limpiar nombres (eliminar "Técnico " si está al inicio)
        $tecnicos = array_map(function($t) {
            // Eliminar "Técnico " al inicio si existe
            $t = preg_replace('/^Técnico\s+/i', '', $t);
            return $t;
        }, $tecnicos);
        
        return $tecnicos;
    }
        
    /**
     * Obtener horas por marca
     */
    public function hoursByBrand(Request $request)
    {
        try {
            $fechaInicio = $request->query('fecha_inicio') 
                ? Carbon::parse($request->query('fecha_inicio'))->startOfDay() 
                : now()->subDays(30)->startOfDay();
            $fechaFin = $request->query('fecha_fin') 
                ? Carbon::parse($request->query('fecha_fin'))->endOfDay() 
                : now()->endOfDay();
                
            $trabajos = HistorialTrabajo::whereBetween('created_at', [$fechaInicio, $fechaFin])->get();
            
            if ($trabajos->isEmpty()) {
                return response()->json([
                    'success' => true, 
                    'data' => [],
                    'message' => 'No hay datos de horas por marca disponibles'
                ]);
            }
            
            $horasPorMarca = [];
            foreach ($trabajos as $trabajo) {
                $marca = $trabajo->marca ?: 'Sin especificar';
                if (!isset($horasPorMarca[$marca])) {
                    $horasPorMarca[$marca] = 0;
                }
                $minutos = $trabajo->created_at->diffInMinutes($trabajo->updated_at);
                $horas = $minutos / 60;
                $horasPorMarca[$marca] += $horas;
            }
            
            if (empty($horasPorMarca)) {
                return response()->json([
                    'success' => true, 
                    'data' => [],
                    'message' => 'No hay datos de horas disponibles'
                ]);
            }
            
            arsort($horasPorMarca);
            $data = array_map(
                fn($marca, $horas) => ['marca' => $marca, 'horas' => round($horas, 2)], 
                array_keys($horasPorMarca), 
                $horasPorMarca
            );
            
            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error en hoursByBrand:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false, 
                'error' => 'Error al cargar horas: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Datos vacíos para estadísticas (fallback)
     */
    private function getEmptyStatsData()
    {
        return [
            'totalVehiculos' => 0,
            'tiempoPromedio' => 0,
            'trabajosRealizados' => 0,
            'fechaInicio' => now()->subDays(30)->format('Y-m-d'),
            'fechaFin' => now()->format('Y-m-d')
        ];
    }
}