<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VehicleController extends Controller
{
    private $nhtsaBaseUrl = 'https://vpic.nhtsa.dot.gov/api/vehicles';
    
    /**
     * Constructor - SIN verificación de API (evita bloqueos)
     */
    public function __construct()
    {
        // Constructor vacío intencionalmente
        // La verificación se hace bajo demanda con caché
    }

    /**
     * Configuración segura para HTTP client - TIMEOUTS REDUCIDOS
     */
    private function makeNhtsaRequest($url)
    {
        // Verificar si la API está disponible según cache
        $apiAvailable = Cache::get('nhtsa_api_available', true);
        if (!$apiAvailable) {
            Log::debug('API no disponible según cache, usando datos locales');
            return null;
        }

        $options = [
            'timeout' => 3,           // ⚡ REDUCIDO: 3 segundos máximo
            'connect_timeout' => 2,   // ⚡ REDUCIDO: 2 segundos para conexión
        ];

        // Solo deshabilitar SSL verification en desarrollo local
        if (app()->environment('local') || app()->environment('development')) {
            $options['verify'] = false;
        }

        try {
            $response = Http::withOptions($options)
                ->retry(1, 100)        // ⚡ 1 reintento rápido, 100ms de espera
                ->get($url);
            
            if ($response->successful()) {
                return $response;
            }
            
            // Si la respuesta no es exitosa, marcar API como no disponible
            Cache::put('nhtsa_api_available', false, 300); // 5 minutos
            return null;
            
        } catch (\Exception $e) {
            Log::warning('NHTSA Request failed: ' . $e->getMessage());
            
            // Marcar API como no disponible temporalmente
            Cache::put('nhtsa_api_available', false, 300); // 5 minutos
            
            return null;
        }
    }

    /**
     * Obtener todas las marcas de vehículos - RESPUESTA INMEDIATA GARANTIZADA
     */
    /**
 * Obtener marcas - VERSIÓN CON DOBLE CACHE
 */
    public function getMakes()
    {
        $apiCacheKey = 'nhtsa_vehicle_makes_api';     // Cache de API real
        $backupCacheKey = 'nhtsa_vehicle_makes_backup'; // Cache de backup
        
        // PRIMERO: Intentar cache de API
        $apiCache = Cache::get($apiCacheKey);
        if ($apiCache) {
            Log::info('Usando marcas desde cache de API');
            return response()->json(json_decode($apiCache, true));
        }
        
        // SEGUNDO: Intentar API
        $response = $this->makeNhtsaRequest("{$this->nhtsaBaseUrl}/getallmakes?format=json");
        
        if ($response && $response->successful()) {
            $data = $response->json();
            
            if (isset($data['Results']) && count($data['Results']) > 0) {
                $allMakes = collect($data['Results'])
                    ->pluck('Make_Name')
                    ->unique()
                    ->sort()
                    ->values()
                    ->toArray();
                
                $mexicoMakes = $this->filterMakesForMexico($allMakes);
                
                $result = [
                    'success' => true,
                    'data' => $mexicoMakes,
                    'source' => 'nhtsa_api',
                    'count' => count($mexicoMakes),
                    'timestamp' => now()->toDateTimeString()
                ];
                
                // Guardar en cache de API
                Cache::put($apiCacheKey, json_encode($result), 3600); // 1 hora
                // También guardar en backup (por si acaso)
                Cache::put($backupCacheKey, json_encode($result), 604800); // 1 semana
                
                return response()->json($result);
            }
        }
        
        // TERCERO: Intentar cache de backup
        $backupCache = Cache::get($backupCacheKey);
        if ($backupCache) {
            Log::info('Usando marcas desde cache de backup');
            $data = json_decode($backupCache, true);
            $data['note'] = 'Usando datos de respaldo - API no disponible';
            return response()->json($data);
        }
        
        // CUARTO: Si no hay nada, generar nuevo backup
        return $this->getBackupMakesResponse();
    }

    /**
     * Obtener años para una marca específica - RESPUESTA INMEDIATA
     */
    public function getYears($make)
    {
        $cacheKey = "nhtsa_vehicle_years_" . md5($make);
        $cacheDuration = 604800; // 1 semana
        
        // PRIMERO: Intentar cache
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return response()->json(json_decode($cached, true));
        }
        
        // SEGUNDO: Intentar API
        $makeForApi = strtoupper($make);
        $response = $this->makeNhtsaRequest("{$this->nhtsaBaseUrl}/GetModelsForMake/{$makeForApi}?format=json");
        
        if ($response && $response->successful()) {
            $data = $response->json();
            
            if (isset($data['Results']) && count($data['Results']) > 0) {
                $years = collect($data['Results'])
                    ->pluck('Model_Year')
                    ->unique()
                    ->filter(function ($year) {
                        return is_numeric($year) && $year >= 1990 && $year <= date('Y') + 1;
                    })
                    ->sortDesc()
                    ->values()
                    ->toArray();
                
                if (!empty($years)) {
                    $result = [
                        'success' => true,
                        'data' => $years,
                        'source' => 'nhtsa',
                        'api_available' => true,
                        'count' => count($years)
                    ];
                    
                    Cache::put($cacheKey, json_encode($result), $cacheDuration);
                    
                    return response()->json($result);
                }
            }
        }
        
        // TERCERO: Datos de respaldo
        return $this->getBackupYearsResponse();
    }

    /**
     * Obtener modelos para una marca y año - RESPUESTA INMEDIATA
     */
    public function getModels($make, $year)
    {
        $cacheKey = "nhtsa_vehicle_models_" . md5("{$make}_{$year}");
        $cacheDuration = 604800; // 1 semana
        
        // PRIMERO: Intentar cache
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return response()->json(json_decode($cached, true));
        }
        
        // SEGUNDO: Intentar API
        $makeForApi = strtoupper($make);
        $response = $this->makeNhtsaRequest(
            "{$this->nhtsaBaseUrl}/GetModelsForMakeYear/make/{$makeForApi}/modelyear/{$year}?format=json"
        );
        
        if ($response && $response->successful()) {
            $data = $response->json();
            
            if (isset($data['Results']) && count($data['Results']) > 0) {
                $models = collect($data['Results'])
                    ->pluck('Model_Name')
                    ->unique()
                    ->filter(function ($model) {
                        return !empty(trim($model)) && $model !== 'NULL';
                    })
                    ->sort()
                    ->values()
                    ->toArray();
                
                if (!empty($models)) {
                    $result = [
                        'success' => true,
                        'data' => $models,
                        'source' => 'nhtsa',
                        'api_available' => true,
                        'count' => count($models)
                    ];
                    
                    Cache::put($cacheKey, json_encode($result), $cacheDuration);
                    
                    return response()->json($result);
                }
            }
        }
        
        // TERCERO: Datos de respaldo
        return $this->getBackupModelsResponse($make);
    }

    /**
     * Búsqueda de vehículos por término
     */
    public function searchVehicles($searchTerm)
    {
        $cacheKey = "nhtsa_vehicle_search_" . md5($searchTerm);
        $cacheDuration = 3600; // 1 hora
        
        // PRIMERO: Intentar cache
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return response()->json(json_decode($cached, true));
        }
        
        // SEGUNDO: Intentar API
        $response = $this->makeNhtsaRequest("{$this->nhtsaBaseUrl}/getallmakes?format=json");
        
        if ($response && $response->successful()) {
            $data = $response->json();
            
            if (isset($data['Results'])) {
                $results = collect($data['Results'])
                    ->filter(function ($item) use ($searchTerm) {
                        return stripos($item['Make_Name'], $searchTerm) !== false;
                    })
                    ->pluck('Make_Name')
                    ->unique()
                    ->sort()
                    ->values()
                    ->toArray();
                
                $result = [
                    'success' => true,
                    'data' => $results,
                    'source' => 'nhtsa',
                    'api_available' => true
                ];
                
                Cache::put($cacheKey, json_encode($result), $cacheDuration);
                
                return response()->json($result);
            }
        }
        
        // TERCERO: Búsqueda en datos locales
        $localMakes = $this->getPriorityMexicoMakes();
        $results = array_values(array_filter($localMakes, function($make) use ($searchTerm) {
            return stripos($make, $searchTerm) !== false;
        }));
        
        $result = [
            'success' => true,
            'data' => $results,
            'source' => 'backup',
            'api_available' => false,
            'note' => 'Búsqueda en datos locales'
        ];
        
        Cache::put($cacheKey, json_encode($result), $cacheDuration);
        
        return response()->json($result);
    }

    /**
     * Endpoint para verificar el estado de la API - RÁPIDO
     */
    public function getApiStatus()
    {
        $apiAvailable = Cache::get('nhtsa_api_available', true);
        
        return response()->json([
            'success' => true,
            'status' => $apiAvailable ? 'online' : 'offline',
            'environment' => app()->environment(),
            'message' => $apiAvailable ? 
                'API NHTSA disponible - usando datos en tiempo real' : 
                'API NHTSA no disponible - usando datos locales de respaldo',
            'timestamp' => now()->toDateTimeString()
        ]);
    }

    /**
     * Respuesta de respaldo para marcas
     */
    private function getBackupMakesResponse()
    {
        $makes = $this->getPriorityMexicoMakes();
        
        $result = [
            'success' => true,
            'data' => $makes,
            'source' => 'backup',
            'api_available' => false,
            'count' => count($makes),
            'note' => 'Datos de respaldo - API no disponible'
        ];
        
        // Cachear por 1 semana
        Cache::put('nhtsa_vehicle_makes_mexico', json_encode($result), 604800);
        
        return response()->json($result);
    }

    /**
     * Respuesta de respaldo para años
     */
    private function getBackupYearsResponse()
    {
        $currentYear = date('Y');
        $years = range($currentYear, $currentYear - 30);
        $years = array_map('strval', $years);
        
        return response()->json([
            'success' => true,
            'data' => $years,
            'source' => 'backup',
            'api_available' => false,
            'note' => 'Datos de respaldo',
            'count' => count($years)
        ]);
    }

    /**
     * Respuesta de respaldo para modelos
     */
    private function getBackupModelsResponse($make)
    {
        $models = $this->getBackupModelsData($make);
        
        return response()->json([
            'success' => true,
            'data' => $models,
            'source' => 'backup',
            'api_available' => false,
            'note' => 'Datos de respaldo',
            'count' => count($models)
        ]);
    }

    /**
     * Datos de respaldo para modelos
     */
    private function getBackupModelsData($make)
    {
        $backupModels = [
            "NISSAN" => ["SENTRA", "ALTIMA", "ROGUE", "MURANO", "PATHFINDER", "MAXIMA", "VERSA", "KICKS", "ARMADA", "FRONTIER", "TITAN", "LEAF"],
            "TOYOTA" => ["COROLLA", "CAMRY", "RAV4", "HIGHLANDER", "TACOMA", "4RUNNER", "PRIUS", "AVALON", "SIENNA", "TUNDRA", "SEQUOIA", "VENZA"],
            "HONDA" => ["CIVIC", "ACCORD", "CR-V", "PILOT", "HR-V", "ODYSSEY", "FIT", "PASSPORT", "RIDGELINE", "INSIGHT"],
            "FORD" => ["F-150", "EXPLORER", "ESCAPE", "MUSTANG", "FOCUS", "FUSION", "EDGE", "RANGER", "BRONCO", "EXPEDITION", "MAVERICK", "TRANSIT"],
            "CHEVROLET" => ["SILVERADO", "EQUINOX", "MALIBU", "TAHOE", "CAMARO", "CRUZE", "TRAVERSE", "COLORADO", "SUBURBAN", "BLAZER", "TRAX", "BOLT"],
            "VOLKSWAGEN" => ["JETTA", "TIGUAN", "ATLAS", "GOLF", "PASSAT", "ARTEON", "ID.4", "TAOS", "ATLAS CROSS SPORT"],
            "BMW" => ["SERIE 3", "SERIE 5", "X3", "X5", "SERIE 7", "X1", "X7", "SERIE 4", "X4", "X6", "I4", "IX"],
            "MERCEDES-BENZ" => ["CLASE C", "CLASE E", "GLC", "GLE", "CLASE S", "CLASE A", "GLA", "GLB", "CLASE G", "EQC", "CLASE CLA"],
            "AUDI" => ["A3", "A4", "Q5", "Q7", "A6", "Q3", "A5", "Q8", "E-TRON", "A7", "Q4 E-TRON"],
            "HYUNDAI" => ["ELANTRA", "SONATA", "TUCSON", "SANTA FE", "ACCENT", "KONA", "PALISADE", "VENUE", "IONIQ", "VELOSTER", "NEXO"],
            "KIA" => ["FORTE", "OPTIMA", "SORENTO", "SPORTAGE", "RIO", "SOUL", "TELLURIDE", "STINGER", "CARNIVAL", "NIRO", "EV6"],
            "MAZDA" => ["MAZDA3", "MAZDA6", "CX-5", "CX-9", "MX-5", "CX-30", "CX-50", "CX-90", "CX-70"],
            "SUBARU" => ["OUTBACK", "FORESTER", "CROSSTREK", "IMPREZA", "LEGACY", "ASCENT", "WRX", "BRZ"],
            "LEXUS" => ["ES", "RX", "NX", "UX", "GX", "LX", "IS", "LS", "RC"],
            "JEEP" => ["WRANGLER", "GRAND CHEROKEE", "CHEROKEE", "COMPASS", "RENEGADE", "GLADIATOR", "WAGONEER"],
            "DODGE" => ["CHARGER", "CHALLENGER", "DURANGO", "HORNET", "GRAND CARAVAN"],
            "CHRYSLER" => ["PACIFICA", "300", "VOYAGER"],
            "GMC" => ["SIERRA", "YUKON", "ACADIA", "TERRAIN", "CANYON", "HUMMER EV"],
            "BUICK" => ["ENCORE", "ENVISION", "ENCLAVE", "REGAL"],
            "CADILLAC" => ["XT5", "XT4", "XT6", "ESCALADE", "CT4", "CT5", "LYRIQ"],
            "ACURA" => ["TLX", "RDX", "MDX", "INTEGRA", "NSX"],
            "INFINITI" => ["Q50", "QX60", "QX80", "QX55", "QX50"],
            "LINCOLN" => ["CORSAIR", "NAUTILUS", "AVIATOR", "NAVIGATOR"],
            "VOLVO" => ["XC60", "XC90", "XC40", "S60", "S90", "C40"],
            "TESLA" => ["MODEL 3", "MODEL Y", "MODEL S", "MODEL X", "CYBERTRUCK"],
            "PORSCHE" => ["911", "CAYENNE", "MACAN", "PANAMERA", "TAYCAN"],
            "JAGUAR" => ["F-PACE", "E-PACE", "I-PACE", "XF", "XJ"],
            "LAND ROVER" => ["RANGE ROVER", "RANGE ROVER SPORT", "DISCOVERY", "DEFENDER", "VELAR"],
            "MITSUBISHI" => ["OUTLANDER", "ECLIPSE CROSS", "MIRAGE", "OUTLANDER SPORT"],
            "MG" => ["MG3", "MG5", "MG ZS", "MG HS", "MG RX5"],
            "CHANGAN" => ["CS35", "CS55", "CS75", "CS85", "EADO"],
            "JAC" => ["J2", "J3", "J4", "J5", "J6", "T6"],
            "DEFAULT" => ["SEDAN", "HATCHBACK", "SUV", "PICKUP", "VAN", "COUPE", "CONVERTIBLE"]
        ];
        
        $makeUpper = strtoupper($make);
        return $backupModels[$makeUpper] ?? $backupModels["DEFAULT"];
    }

    /**
     * Lista prioritaria de marcas para México
     */
    private function getPriorityMexicoMakes()
    {
        return [
            "NISSAN", "TOYOTA", "VOLKSWAGEN", "CHEVROLET", "FORD", "HYUNDAI", "KIA", "HONDA",
            "MAZDA", "BMW", "MERCEDES-BENZ", "AUDI", "MITSUBISHI", "SUBARU",
            "LEXUS", "VOLVO", "PORSCHE", "LAND ROVER", "JAGUAR", "MINI", "ACURA", "INFINITI",
            "DODGE", "CHRYSLER", "RAM", "GMC", "BUICK", "CADILLAC", "LINCOLN", "FIAT", "RENAULT", 
            "PEUGEOT", "CITROEN", "SEAT", "SKODA", "SUZUKI", "ISUZU",
            "MG", "CHANGAN", "JAC",
            "TESLA", "JEEP", "ALFA ROMEO", "MASERATI", "BENTLEY", "ROLLS-ROYCE", "FERRARI", "LAMBORGHINI"
        ];
    }

    /**
     * Filtrar marcas para el mercado mexicano
     */
    private function filterMakesForMexico($allMakes)
    {
        $mexicoBrands = $this->getPriorityMexicoMakes();
        $mexicoBrandsUpper = array_map('strtoupper', $mexicoBrands);
        
        $filteredMakes = array_filter($allMakes, function($make) use ($mexicoBrandsUpper) {
            $makeUpper = strtoupper($make);
            return in_array($makeUpper, $mexicoBrandsUpper);
        });
        
        sort($filteredMakes);
        return array_values($filteredMakes);
    }

    /**
     * Endpoint para forzar la actualización del cache
     */
    public function refreshCache()
    {
        try {
            Cache::forget('nhtsa_api_available');
            Cache::forget('nhtsa_vehicle_makes_mexico');
            
            $popularMakes = ['NISSAN', 'TOYOTA', 'HONDA', 'FORD', 'CHEVROLET', 'VOLKSWAGEN'];
            foreach ($popularMakes as $make) {
                Cache::forget("nhtsa_vehicle_years_" . md5($make));
                Cache::forget("nhtsa_vehicle_models_" . md5("{$make}_" . date('Y')));
            }
            
            Log::info('Vehicle cache refreshed successfully');
            
            return response()->json([
                'success' => true,
                'message' => 'Cache refrescado exitosamente'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error refreshing vehicle cache: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error refrescando cache'
            ]);
        }
    }

    /**
     * Endpoint para obtener información del sistema
     */
    public function getSystemInfo()
    {
        $apiAvailable = Cache::get('nhtsa_api_available', true);
        
        return response()->json([
            'success' => true,
            'data' => [
                'environment' => app()->environment(),
                'api_status' => $apiAvailable ? 'online' : 'offline',
                'cache_driver' => config('cache.default'),
                'fallback_data' => 'available',
                'last_checked' => now()->toDateTimeString(),
                'note' => $apiAvailable ? 
                    'API NHTSA disponible - usando datos en tiempo real' : 
                    'API NHTSA no disponible - usando datos locales de respaldo'
            ]
        ]);
    }

    /**
     * Endpoint de emergencia: forzar modo offline
     */
    public function forceOfflineMode()
    {
        Cache::put('nhtsa_api_available', false, 3600); // 1 hora
        
        return response()->json([
            'success' => true,
            'message' => 'Modo offline activado por 1 hora',
            'timestamp' => now()->toDateTimeString()
        ]);
    }

    /**
     * Endpoint de emergencia: forzar modo online
     */
    public function forceOnlineMode()
    {
        Cache::forget('nhtsa_api_available');
        
        return response()->json([
            'success' => true,
            'message' => 'Modo online activado',
            'timestamp' => now()->toDateTimeString()
        ]);
    }
}