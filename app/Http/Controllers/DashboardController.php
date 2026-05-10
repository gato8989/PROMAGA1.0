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
                
            $trabajos = HistorialTrabajo::whereBetween('created_at', [$fechaInicio, $fechaFin])->get();
            
            $totalVehiculos = $trabajos->count();
            $tiempoPromedio = 0;
            $trabajosRealizados = 0;
            
            if ($totalVehiculos > 0) {
                $tiempos = [];
                foreach ($trabajos as $trabajo) {
                    $entrada = $this->parseFecha($trabajo->fecha_ingreso, $trabajo->hora_creacion);
                    $salida = $this->parseFecha($trabajo->fecha_terminado, $trabajo->hora_terminado);
                    
                    if ($entrada && $salida) {
                        $minutos = $entrada->diffInMinutes($salida);
                        if ($minutos > 0) {
                            $tiempos[] = $minutos;
                        }
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
     * Obtener tendencia diaria - CORREGIDO
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
                
            $trabajos = HistorialTrabajo::whereBetween('created_at', [$fechaInicio, $fechaFin])->get();
            
            // Crear un array con todos los días del rango (inicializados en 0)
            $tendencia = [];
            $currentDate = $fechaInicio->copy();
            
            while ($currentDate <= $fechaFin) {
                $fechaKey = $currentDate->format('Y-m-d');
                $tendencia[$fechaKey] = [
                    'fecha' => $fechaKey,
                    'vehiculos' => 0,
                    'horas' => 0
                ];
                $currentDate->addDay();
            }
            
            // Agregar los datos reales de los trabajos
            foreach ($trabajos as $trabajo) {
                // Parsear la fecha de terminado
                $fechaTerminado = $this->parseFecha($trabajo->fecha_terminado, null);
                
                if (!$fechaTerminado) {
                    \Illuminate\Support\Facades\Log::warning('Fecha no parseable:', [
                        'id' => $trabajo->id,
                        'fecha_terminado' => $trabajo->fecha_terminado
                    ]);
                    continue;
                }
                
                // Obtener la fecha como string en formato Y-m-d
                $fechaKey = $fechaTerminado->format('Y-m-d');
                
                // Log para depuración
                \Illuminate\Support\Facades\Log::info('Procesando trabajo:', [
                    'id' => $trabajo->id,
                    'fecha_original' => $trabajo->fecha_terminado,
                    'fecha_parseada' => $fechaKey
                ]);
                
                // Si la fecha está dentro del rango, agregar los datos
                if (isset($tendencia[$fechaKey])) {
                    $tendencia[$fechaKey]['vehiculos']++;
                    
                    // Calcular horas del trabajo
                    $entrada = $this->parseFecha($trabajo->fecha_ingreso, $trabajo->hora_creacion);
                    $salida = $this->parseFecha($trabajo->fecha_terminado, $trabajo->hora_terminado);
                    
                    if ($entrada && $salida) {
                        $minutos = $entrada->diffInMinutes($salida);
                        $horas = $minutos / 60;
                        $tendencia[$fechaKey]['horas'] += $horas;
                    }
                } else {
                    \Illuminate\Support\Facades\Log::warning('Fecha fuera de rango:', [
                        'fecha' => $fechaKey,
                        'rango_inicio' => $fechaInicio->format('Y-m-d'),
                        'rango_fin' => $fechaFin->format('Y-m-d'),
                        'fecha_original' => $trabajo->fecha_terminado
                    ]);
                }
            }
            
            // Convertir el array asociativo a un array indexado y ordenar
            $resultado = array_values($tendencia);
            
            // Ordenar por fecha
            usort($resultado, function($a, $b) {
                return strcmp($a['fecha'], $b['fecha']);
            });
            
            // Redondear horas
            foreach ($resultado as &$item) {
                $item['horas'] = round($item['horas'], 2);
            }
            
            // Log para depuración (puedes eliminarlo después)
            \Illuminate\Support\Facades\Log::info('Tendencia generada:', [
                'total_dias' => count($resultado),
                'dias_con_datos' => count(array_filter($resultado, fn($d) => $d['vehiculos'] > 0)),
                'rango' => $fechaInicio->format('Y-m-d') . ' - ' . $fechaFin->format('Y-m-d')
            ]);
            
            return response()->json(['success' => true, 'data' => $resultado]);
            
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
        // ... (sin cambios, igual que antes)
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
        // ... (sin cambios, igual que antes)
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
        // ... (sin cambios, igual que antes)
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
        // ... (sin cambios importantes)
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
     * Obtener tiempos de trabajo - MODIFICADO: solo trabajos comunes
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
                
            $trabajos = HistorialTrabajo::whereBetween('created_at', [$fechaInicio, $fechaFin])->get();
            
            if ($trabajos->isEmpty()) {
                return response()->json([
                    'success' => true, 
                    'data' => [],
                    'message' => 'No hay datos de tiempos disponibles'
                ]);
            }
            
            // Primero obtener los trabajos más comunes (top 10)
            $trabajosConteo = [];
            foreach ($trabajos as $trabajo) {
                if (is_array($trabajo->trabajos)) {
                    foreach ($trabajo->trabajos as $work) {
                        $workClean = trim($work);
                        if (!empty($workClean)) {
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
                    'message' => 'No hay datos de trabajos disponibles'
                ]);
            }
            
            arsort($trabajosConteo);
            $topTrabajos = array_slice($trabajosConteo, 0, 10, true);
            $trabajosTopNombres = array_keys($topTrabajos);
            
            // Calcular tiempos solo para los trabajos más comunes
            $tiemposPorTrabajo = [];
            
            foreach ($trabajos as $trabajo) {
                $trabajosRealizados = $trabajo->trabajos;
                
                $entrada = $this->parseFecha($trabajo->fecha_ingreso, $trabajo->hora_creacion);
                $salida = $this->parseFecha($trabajo->fecha_terminado, $trabajo->hora_terminado);
                
                if (!$entrada || !$salida) {
                    continue;
                }
                
                $minutos = $entrada->diffInMinutes($salida);
                $horas = $minutos / 60;
                $horas = round($horas, 2);
                
                if (is_array($trabajosRealizados) && !empty($trabajosRealizados)) {
                    foreach ($trabajosRealizados as $work) {
                        $workClean = trim($work);
                        if (empty($workClean)) continue;
                        
                        $workClean = preg_replace('/[^a-zA-Z0-9áéíóúñÑüÜ\s\-]/u', '', $workClean);
                        $workClean = trim($workClean);
                        
                        // Solo procesar si el trabajo está en el top 10
                        if (!empty($workClean) && in_array($workClean, $trabajosTopNombres)) {
                            if (!isset($tiemposPorTrabajo[$workClean])) {
                                $tiemposPorTrabajo[$workClean] = [];
                            }
                            $tiemposPorTrabajo[$workClean][] = $horas;
                        }
                    }
                }
            }
            
            if (empty($tiemposPorTrabajo)) {
                return response()->json([
                    'success' => true, 
                    'data' => [],
                    'message' => 'No hay datos de tiempos disponibles'
                ]);
            }
            
            $data = [];
            foreach ($tiemposPorTrabajo as $trabajo => $tiempos) {
                if (!empty($tiempos)) {
                    $data[] = [
                        'trabajo' => $trabajo, 
                        'minimo' => round(min($tiempos), 2),
                        'promedio' => round(array_sum($tiempos) / count($tiempos), 2),
                        'maximo' => round(max($tiempos), 2)
                    ];
                }
            }
            
            // Ordenar por el mismo orden que los trabajos más comunes
            $orderedData = [];
            foreach ($trabajosTopNombres as $nombreTrabajo) {
                foreach ($data as $item) {
                    if ($item['trabajo'] === $nombreTrabajo) {
                        $orderedData[] = $item;
                        break;
                    }
                }
            }
            
            return response()->json(['success' => true, 'data' => $orderedData]);
            
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
     * Obtener horas por marca - CORREGIDO
     */
    public function hoursByBrand(Request $request)
    {
        try {
            $fechaInicio = $request->query('fecha_inicio') 
                ? Carbon::parse($request->query('fecha_inicio'))->startOfDay()->setTimezone('UTC')
                : now()->subDays(30)->startOfDay()->setTimezone('UTC');
            $fechaFin = $request->query('fecha_fin') 
                ? Carbon::parse($request->query('fecha_fin'))->endOfDay()->setTimezone('UTC')
                : now()->endOfDay()->setTimezone('UTC');
                
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
                
                $entrada = $this->parseFecha($trabajo->fecha_ingreso, $trabajo->hora_creacion);
                $salida = $this->parseFecha($trabajo->fecha_terminado, $trabajo->hora_terminado);
                
                if (!$entrada || !$salida) {
                    continue;
                }
                
                // Asegurar zona horaria UTC
                $entrada->setTimezone('UTC');
                $salida->setTimezone('UTC');
                
                $minutos = $entrada->diffInMinutes($salida);
                $horas = $minutos / 60;
                
                if (!isset($horasPorMarca[$marca])) {
                    $horasPorMarca[$marca] = 0;
                }
                
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
            
            // Tomar solo las primeras 10 marcas
            $topMarcas = array_slice($horasPorMarca, 0, 10, true);
            
            $data = array_map(
                fn($marca, $horas) => ['marca' => $marca, 'horas' => round($horas, 2)], 
                array_keys($topMarcas), 
                $topMarcas
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
     * Obtener rendimiento de técnicos
     */
    public function technicianPerformance(Request $request)
    {
        // ... (sin cambios, igual que antes)
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
                    'message' => 'No hay datos de técnicos disponibles'
                ]);
            }
            
            $tecnicosData = [];
            
            foreach ($trabajos as $trabajo) {
                $tecnicoRaw = $trabajo->usuario_termino ?: 'Sin asignar';
                $tecnicos = $this->extractTechnicians($tecnicoRaw);
                
                $entrada = $this->parseFecha($trabajo->fecha_ingreso, $trabajo->hora_creacion);
                $salida = $this->parseFecha($trabajo->fecha_terminado, $trabajo->hora_terminado);
                
                if (!$entrada || !$salida) {
                    continue;
                }
                
                $minutos = $entrada->diffInMinutes($salida);
                $horas = $minutos / 60;
                
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
            
            if (empty($tecnicosData)) {
                return response()->json([
                    'success' => true, 
                    'data' => [],
                    'message' => 'No hay datos de técnicos disponibles'
                ]);
            }
            
            $maxTrabajos = 0;
            foreach ($tecnicosData as $tecnico) {
                $maxTrabajos = max($maxTrabajos, $tecnico['vehiculos_trabajados']);
            }
            
            $result = [];
            foreach ($tecnicosData as $tecnico) {
                $promedio = 0;
                if (count($tecnico['tiempos']) > 0) {
                    $promedio = round(array_sum($tecnico['tiempos']) / count($tecnico['tiempos']), 2);
                }
                
                $porcentajeTrabajos = $maxTrabajos > 0 ? ($tecnico['vehiculos_trabajados'] / $maxTrabajos) * 100 : 0;
                
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
            
            usort($result, fn($a, $b) => $b['horas_trabajadas'] <=> $a['horas_trabajadas']);
            
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
     * Parsear fecha y hora del historial - FORZANDO FECHA EXACTA sin conversión de zona horaria
     * El formato de fecha es "d/m/Y" (ej: 9/5/2026 o 04/05/2026)
     */
    private function parseFecha($fechaStr, $horaStr = null)
    {
        if (empty($fechaStr)) {
            return null;
        }
        
        try {
            // Limpiar la fecha (eliminar espacios)
            $fechaStr = trim($fechaStr);
            
            // Parsear fecha en formato d/m/Y
            $partes = explode('/', $fechaStr);
            if (count($partes) !== 3) {
                // Intentar con guiones
                $partes = explode('-', $fechaStr);
                if (count($partes) !== 3) {
                    return null;
                }
            }
            
            $dia = intval($partes[0]);
            $mes = intval($partes[1]);
            $año = intval($partes[2]);
            
            // Validar que el año tenga 4 dígitos
            if ($año < 1000 || $año > 9999) {
                return null;
            }
            
            if (!checkdate($mes, $dia, $año)) {
                return null;
            }
            
            // IMPORTANTE: Usar createFromFormat para evitar conversiones de zona horaria
            // Esto crea una fecha EXACTAMENTE con los valores proporcionados
            $fechaStrCompleta = sprintf('%04d-%02d-%02d', $año, $mes, $dia);
            
            if ($horaStr) {
                // Parsear hora en formato H:i:s o H:i
                $horaStr = trim($horaStr);
                $horaPartes = explode(':', $horaStr);
                if (count($horaPartes) >= 2) {
                    $horas = intval($horaPartes[0]);
                    $minutos = intval($horaPartes[1]);
                    $segundos = count($horaPartes) >= 3 ? intval($horaPartes[2]) : 0;
                    $fechaStrCompleta .= sprintf(' %02d:%02d:%02d', $horas, $minutos, $segundos);
                } else {
                    $fechaStrCompleta .= ' 00:00:00';
                }
            } else {
                $fechaStrCompleta .= ' 00:00:00';
            }
            
            // Usar createFromFormat sin zona horaria para evitar conversiones
            $fecha = Carbon::createFromFormat('Y-m-d H:i:s', $fechaStrCompleta);
            
            return $fecha;
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error parseando fecha:', [
                'fecha' => $fechaStr,
                'hora' => $horaStr,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    /**
     * Extraer todos los técnicos de un string
     */
    private function extractTechnicians($tecnicoString)
    {
        $tecnicos = [];
        
        if (preg_match('/\(Colaboraron:\s*([^)]+)\)/i', $tecnicoString, $matches)) {
            $tecnicoPrincipal = trim(preg_replace('/\s*\(Colaboraron:.*\)/i', '', $tecnicoString));
            if (!empty($tecnicoPrincipal)) {
                $tecnicos[] = $tecnicoPrincipal;
            }
            
            $colaboradores = explode(',', $matches[1]);
            foreach ($colaboradores as $colaborador) {
                $colaborador = trim($colaborador);
                if (!empty($colaborador) && !in_array($colaborador, $tecnicos)) {
                    $tecnicos[] = $colaborador;
                }
            }
        } else {
            $tecnicos[] = trim($tecnicoString);
        }
        
        $tecnicos = array_map(function($t) {
            return preg_replace('/^Técnico\s+/i', '', $t);
        }, $tecnicos);
        
        return $tecnicos;
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