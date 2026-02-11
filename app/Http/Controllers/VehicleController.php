<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VehicleController extends Controller
{
    private $nhtsaBaseUrl = 'https://vpic.nhtsa.dot.gov/api/vehicles';
    private $apiAvailable = true;

    /**
     * Constructor - Verificar disponibilidad de API al inicio
     */
    public function __construct()
    {
        $this->checkApiAvailability();
    }

    /**
     * Verificar si la API está disponible
     */
    private function checkApiAvailability()
    {
        try {
            // Verificar cache primero
            $cacheKey = 'nhtsa_api_available';
            $cachedAvailability = Cache::get($cacheKey);
            
            if ($cachedAvailability !== null) {
                $this->apiAvailable = $cachedAvailability;
                return;
            }
            
            // Intentar conexión simple con timeout corto
            $response = Http::timeout(5)
                ->connectTimeout(3)
                ->get("{$this->nhtsaBaseUrl}/getallmakes?format=json");
            
            $this->apiAvailable = $response->successful();
            
            // Cachear resultado por 5 minutos
            Cache::put($cacheKey, $this->apiAvailable, 300);
            
        } catch (\Exception $e) {
            $this->apiAvailable = false;
            Cache::put($cacheKey, false, 300);
            Log::warning('NHTSA API no disponible: ' . $e->getMessage());
        }
    }

    /**
     * Configuración segura para HTTP client
     */
    private function makeNhtsaRequest($url)
    {
        // Si sabemos que la API no está disponible, no intentar
        if (!$this->apiAvailable) {
            return null;
        }

        $options = [
            'timeout' => 10,
            'connect_timeout' => 5,
        ];

        // Solo deshabilitar SSL verification en desarrollo local
        if (app()->environment('local') || app()->environment('development')) {
            $options['verify'] = false;
        }

        try {
            return Http::withOptions($options)
                ->retry(2, 500) // Solo 2 reintentos rápidos
                ->get($url);
        } catch (\Exception $e) {
            Log::warning("NHTSA Request failed: " . $e->getMessage());
            
            // Marcar API como no disponible temporalmente
            Cache::put('nhtsa_api_available', false, 60); // 1 minuto
            
            return null;
        }
    }

    /**
     * Obtener todas las marcas de vehículos - CON FALLBACK AUTOMÁTICO
     */
    public function getMakes()
    {
        $cacheKey = 'nhtsa_vehicle_makes_mexico';
        $cacheDuration = 604800; // 1 semana
        
        return Cache::remember($cacheKey, $cacheDuration, function () {
            // Primero intentar con API
            if ($this->apiAvailable) {
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
                        
                        if (!empty($mexicoMakes)) {
                            Log::info('Marcas obtenidas de API NHTSA', ['count' => count($mexicoMakes)]);
                            return response()->json([
                                'success' => true,
                                'data' => $mexicoMakes,
                                'source' => 'nhtsa_filtered',
                                'api_available' => true
                            ]);
                        }
                    }
                }
            }
            
            // Si llegamos aquí, usar datos de respaldo
            Log::info('Usando datos de respaldo para marcas');
            return $this->getBackupMakesResponse();
        });
    }

    /**
     * Respuesta de respaldo para marcas
     */
    private function getBackupMakesResponse()
    {
        $makes = $this->getPriorityMexicoMakes();
        
        return response()->json([
            'success' => true,
            'data' => $makes,
            'source' => 'backup',
            'api_available' => false,
            'count' => count($makes),
            'note' => 'Datos de respaldo - API no disponible'
        ]);
    }

    /**
     * Obtener años para una marca específica - CON FALLBACK
     */
    public function getYears($make)
    {
        $cacheKey = "nhtsa_vehicle_years_" . md5($make);
        $cacheDuration = 604800;
        
        return Cache::remember($cacheKey, $cacheDuration, function () use ($make) {
            Log::info("Fetching years for make: {$make}");
            
            // Primero intentar con API
            if ($this->apiAvailable) {
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
                            Log::info("Años obtenidos de API", ['count' => count($years)]);
                            return response()->json([
                                'success' => true,
                                'data' => $years,
                                'source' => 'nhtsa',
                                'api_available' => true
                            ]);
                        }
                    }
                }
            }
            
            // Fallback a datos locales
            Log::info("Usando datos de respaldo para años de: {$make}");
            return $this->getBackupYearsResponse();
        });
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
            'note' => 'Datos de respaldo'
        ]);
    }

    /**
     * Obtener modelos para una marca y año - CON FALLBACK
     */
    public function getModels($make, $year)
    {
        $cacheKey = "nhtsa_vehicle_models_" . md5("{$make}_{$year}");
        $cacheDuration = 604800;
        
        return Cache::remember($cacheKey, $cacheDuration, function () use ($make, $year) {
            Log::info("Fetching models for make: {$make}, year: {$year}");
            
            // Primero intentar con API
            if ($this->apiAvailable) {
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
                            Log::info("Modelos obtenidos de API", ['count' => count($models)]);
                            return response()->json([
                                'success' => true,
                                'data' => $models,
                                'source' => 'nhtsa',
                                'api_available' => true
                            ]);
                        }
                    }
                }
            }
            
            // Fallback a datos locales
            Log::info("Usando datos de respaldo para modelos de: {$make}");
            return $this->getBackupModelsResponse($make);
        });
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
            'note' => 'Datos de respaldo'
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
            // Marca genérica para cualquier otra marca
            "DEFAULT" => ["SEDAN", "HATCHBACK", "SUV", "PICKUP", "VAN", "COUPE", "CONVERTIBLE"]
        ];
        
        $makeUpper = strtoupper($make);
        return $backupModels[$makeUpper] ?? $backupModels["DEFAULT"];
    }

    /**
     * Búsqueda de vehículos por término - CON FALLBACK
     */
    public function searchVehicles($searchTerm)
    {
        $cacheKey = "nhtsa_vehicle_search_" . md5($searchTerm);
        $cacheDuration = 3600;
        
        return Cache::remember($cacheKey, $cacheDuration, function () use ($searchTerm) {
            if ($this->apiAvailable) {
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
                        
                        return response()->json([
                            'success' => true,
                            'data' => $results,
                            'source' => 'nhtsa',
                            'api_available' => true
                        ]);
                    }
                }
            }
            
            // Fallback: buscar en datos locales
            $localMakes = $this->getPriorityMexicoMakes();
            $results = array_filter($localMakes, function($make) use ($searchTerm) {
                return stripos($make, $searchTerm) !== false;
            });
            
            return response()->json([
                'success' => true,
                'data' => array_values($results),
                'source' => 'backup',
                'api_available' => false,
                'note' => 'Búsqueda en datos locales'
            ]);
        });
    }

    /**
     * Endpoint para verificar el estado de la API
     */
    public function getApiStatus()
    {
        try {
            // Verificar estado actual
            $apiAvailable = Cache::get('nhtsa_api_available', true);
            
            // Intentar una conexión rápida si pensamos que está disponible
            if ($apiAvailable) {
                $testResponse = Http::timeout(3)
                    ->connectTimeout(2)
                    ->get("{$this->nhtsaBaseUrl}/getallmakes?format=json");
                
                $apiAvailable = $testResponse->successful();
                Cache::put('nhtsa_api_available', $apiAvailable, 300);
            }
            
            return response()->json([
                'success' => true,
                'status' => $apiAvailable ? 'online' : 'offline',
                'environment' => app()->environment(),
                'message' => $apiAvailable ? 'API NHTSA disponible' : 'API NHTSA no disponible - Usando datos locales',
                'timestamp' => now()->toDateTimeString()
            ]);
            
        } catch (\Exception $e) {
            Cache::put('nhtsa_api_available', false, 300);
            
            return response()->json([
                'success' => true,
                'status' => 'offline',
                'environment' => app()->environment(),
                'message' => 'API NHTSA no disponible - Usando datos locales',
                'error' => $e->getMessage(),
                'timestamp' => now()->toDateTimeString()
            ]);
        }
    }

    /**
     * Datos de respaldo para marcas (YA FILTRADO para México)
     */
    private function getPriorityMexicoMakes()
    {
        return [
            // Nivel 1: Marcas más populares
            "NISSAN", "TOYOTA", "VOLKSWAGEN", "CHEVROLET", "FORD", "HYUNDAI", "KIA", "HONDA",
            
            // Nivel 2: Marcas con buena presencia
            "MAZDA", "BMW", "MERCEDES-BENZ", "AUDI", "MITSUBISHI", "SUBARU",
            
            // Nivel 3: Marcas de lujo
            "LEXUS", "VOLVO", "PORSCHE", "LAND ROVER", "JAGUAR", "MINI", "ACURA", "INFINITI",
            
            // Nivel 4: Otras marcas
            "DODGE", "CHRYSLER", "RAM", "GMC", "BUICK", "CADILLAC", "LINCOLN", "FIAT", "RENAULT", 
            "PEUGEOT", "CITROEN", "SEAT", "SKODA", "SUZUKI", "ISUZU",
            
            // Nivel 5: Marcas chinas
            "MG", "CHANGAN", "JAC",
            
            // Marcas adicionales comunes
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
            // Limpiar caches principales
            Cache::forget('nhtsa_api_available');
            Cache::forget('nhtsa_vehicle_makes_mexico');
            
            // Limpiar caches de años y modelos
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
                'cache_ttl_makes' => '1 semana',
                'cache_ttl_years_models' => '1 semana',
                'failover_system' => 'active',
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
            'timestamp' => now()->toDateTimeString(),
            'note' => 'El sistema usará exclusivamente datos locales'
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
            'timestamp' => now()->toDateTimeString(),
            'note' => 'El sistema intentará usar la API NHTSA'
        ]);
    }
}