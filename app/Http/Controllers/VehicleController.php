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
     * Configuración segura para HTTP client
     */
    private function makeNhtsaRequest($url)
    {
        $options = [
            'timeout' => 15,
            'connect_timeout' => 10,
        ];

        // Solo deshabilitar SSL verification en desarrollo local
        if (app()->environment('local') || app()->environment('development')) {
            $options['verify'] = false;
        }

        try {
            return Http::withOptions($options)
                ->retry(3, 1000)
                ->get($url);
        } catch (\Exception $e) {
            Log::error("NHTSA Request failed for URL: {$url} - Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtener todas las marcas de vehículos - FILTRADO PARA MÉXICO
     */
    public function getMakes()
    {
        return Cache::remember('nhtsa_vehicle_makes_mexico', 604800, function () {
            $response = $this->makeNhtsaRequest("{$this->nhtsaBaseUrl}/getallmakes?format=json");
            
            if ($response && $response->successful()) {
                $data = $response->json();
                
                if (isset($data['Results']) && count($data['Results']) > 0) {
                    // Primero obtener todas las marcas
                    $allMakes = collect($data['Results'])
                        ->pluck('Make_Name')
                        ->unique()
                        ->sort()
                        ->values()
                        ->toArray();
                    
                    Log::info('Total marcas from NHTSA API', ['count' => count($allMakes)]);
                    
                    // Filtrar para marcas disponibles en México
                    $mexicoMakes = $this->filterMakesForMexico($allMakes);
                    
                    Log::info('Marcas filtradas para México', [
                        'total' => count($allMakes),
                        'filtradas' => count($mexicoMakes)
                    ]);
                    
                    return response()->json([
                        'success' => true,
                        'data' => $mexicoMakes,
                        'source' => 'nhtsa_filtered',
                        'count' => count($mexicoMakes),
                        'note' => 'Marcas filtradas para mercado mexicano'
                    ]);
                }
            }
            
            // Si falla la API, usar datos de respaldo ya filtrados
            Log::info('Using filtered backup makes data for Mexico');
            return $this->getBackupMakes();
        });
    }

    /**
     * Filtrar marcas para el mercado mexicano
     */
    private function filterMakesForMexico($allMakes)
    {
        // Marcas principales disponibles en México (basado en popularidad y presencia)
        $mexicoBrands = [
            // Marcas Japonesas (muy populares en México)
            'TOYOTA', 'HONDA', 'NISSAN', 'MAZDA', 'SUBARU', 'MITSUBISHI', 'SUZUKI', 'ISUZU',
            
            // Marcas Americanas
            'FORD', 'CHEVROLET', 'DODGE', 'JEEP', 'CHRYSLER', 'GMC', 'BUICK', 'CADILLAC', 'LINCOLN', 'RAM',
            
            // Marcas Coreanas
            'HYUNDAI', 'KIA', 
            
            // Marcas Europeas (comunes en México)
            'VOLKSWAGEN', 'BMW', 'MERCEDES-BENZ', 'AUDI', 'VOLVO', 'RENAULT', 'PEUGEOT', 
            'CITROEN', 'FIAT', 'SEAT', 'SKODA', 'MINI', 'PORSCHE', 'JAGUAR', 'LAND ROVER',
            
            // Marcas de lujo (presentes en México)
            'LEXUS', 'ACURA', 'INFINITI', 
            
            // Marcas Chinas (que están entrando al mercado mexicano)
            'MG', 'CHANGAN', 'JAC', 'BAIC', 'BRILLIANCE', 'GEELY',
            
            // Marcas de volumen bajo o especializadas
            'ALFA ROMEO', 'MASERATI', 'FERRARI', 'LAMBORGHINI', 'BENTLEY', 'ROLLS-ROYCE',
            'ASTON MARTIN', 'MCLAREN', 'LOTUS'
        ];
        
        // Convertir a mayúsculas para comparación case-insensitive
        $mexicoBrandsUpper = array_map('strtoupper', $mexicoBrands);
        
        // Filtrar marcas que están en nuestra lista de México
        $filteredMakes = array_filter($allMakes, function($make) use ($mexicoBrandsUpper) {
            $makeUpper = strtoupper($make);
            return in_array($makeUpper, $mexicoBrandsUpper);
        });
        
        // Si no encontramos suficientes marcas, usar nuestra lista predefinida
        if (count($filteredMakes) < 20) {
            Log::warning('Few brands found for Mexico, using predefined list', [
                'found' => count($filteredMakes),
                'expected' => count($mexicoBrands)
            ]);
            return $this->getPriorityMexicoMakes();
        }
        
        // Ordenar alfabéticamente
        sort($filteredMakes);
        
        return array_values($filteredMakes);
    }

    /**
     * Lista prioritaria de marcas para México (ordenadas por popularidad)
     */
    private function getPriorityMexicoMakes()
    {
        // Marcas ordenadas por popularidad en México
        $priorityMakes = [
            // Nivel 1: Marcas más populares y vendidas
            "NISSAN", "TOYOTA", "VOLKSWAGEN", "CHEVROLET", "FORD", "HYUNDAI", "KIA", "HONDA",
            
            // Nivel 2: Marcas con buena presencia
            "MAZDA", "BMW", "MERCEDES-BENZ", "AUDI", "MITSUBISHI", "SUBARU",
            
            // Nivel 3: Marcas de lujo y especializadas
            "LEXUS", "VOLVO", "PORSCHE", "LAND ROVER", "JAGUAR", "MINI", "ACURA", "INFINITI",
            
            // Nivel 4: Otras marcas disponibles
            "DODGE", "CHRYSLER", "RAM", "GMC", "BUICK", "CADILLAC", "LINCOLN", "FIAT", "RENAULT", 
            "PEUGEOT", "CITROEN", "SEAT", "SKODA", "SUZUKI", "ISUZU",
            
            // Nivel 5: Marcas chinas emergentes
            "MG", "CHANGAN", "JAC"
        ];
        
        // Eliminar duplicados y ordenar
        $priorityMakes = array_unique($priorityMakes);
        sort($priorityMakes);
        
        return $priorityMakes;
    }

    /**
     * Obtener años para una marca específica - VERSIÓN CORREGIDA
     */
    public function getYears($make)
    {
        $cacheKey = "nhtsa_vehicle_years_" . md5($make);
        
        return Cache::remember($cacheKey, 604800, function () use ($make) {
            Log::info("Fetching years for make: {$make}");
            
            // Convertir a mayúsculas para la API NHTSA
            $makeForApi = strtoupper($make);
            
            // Primero intentar con el nombre en mayúsculas
            $response = $this->makeNhtsaRequest("{$this->nhtsaBaseUrl}/GetModelsForMake/{$makeForApi}?format=json");
            
            if ($response && $response->successful()) {
                $data = $response->json();
                Log::info("NHTSA API Response Status: " . $response->status());
                Log::info("NHTSA API Data Received", ['results_count' => count($data['Results'] ?? [])]);
                
                if (isset($data['Results']) && count($data['Results']) > 0) {
                    $years = collect($data['Results'])
                        ->pluck('Model_Year')
                        ->unique()
                        ->filter(function ($year) {
                            $isValid = is_numeric($year) && $year >= 1990 && $year <= date('Y') + 1;
                            return $isValid;
                        })
                        ->sortDesc()
                        ->values()
                        ->toArray();
                    
                    if (!empty($years)) {
                        Log::info("Successfully processed years for {$makeForApi}", [
                            'count' => count($years),
                            'years_sample' => array_slice($years, 0, 5)
                        ]);
                        
                        return response()->json([
                            'success' => true,
                            'data' => $years,
                            'source' => 'nhtsa',
                            'count' => count($years)
                        ]);
                    }
                }
            }
            
            // Si falla, intentar con nombres alternativos
            $alternativeMakes = $this->getAlternativeMakeNames($make);
            
            foreach ($alternativeMakes as $alternativeMake) {
                Log::info("Trying alternative make name: {$alternativeMake}");
                
                $response = $this->makeNhtsaRequest("{$this->nhtsaBaseUrl}/GetModelsForMake/{$alternativeMake}?format=json");
                
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
                            Log::info("Successfully processed years using alternative name", [
                                'original' => $make,
                                'alternative' => $alternativeMake,
                                'count' => count($years)
                            ]);
                            
                            return response()->json([
                                'success' => true,
                                'data' => $years,
                                'source' => 'nhtsa_alternative',
                                'count' => count($years),
                                'note' => "Usando nombre alternativo: {$alternativeMake}"
                            ]);
                        }
                    }
                }
            }
            
            // Si todo falla, usar datos de respaldo
            Log::warning("All attempts failed for make: {$make}, using backup data");
            return $this->getBackupYears();
        });
    }

    /**
     * Obtener nombres alternativos para una marca (en mayúsculas para NHTSA)
     */
    private function getAlternativeMakeNames($make)
    {
        $makeUpper = strtoupper($make);
        
        $alternatives = [
            'GENERAL MOTORS' => ['GM', 'CHEVROLET', 'GMC', 'BUICK', 'CADILLAC'],
            'MERCEDES-BENZ' => ['MERCEDES'],
            'ALFA ROMEO' => ['ALFA'],
            'LAND ROVER' => ['LANDROVER'],
            'ASTON MARTIN' => ['ASTON'],
            'ROLLS-ROYCE' => ['ROLLSROYCE'],
            'MAZDA' => ['MAZDA'],
            'TOYOTA' => ['TOYOTA'],
            'HONDA' => ['HONDA'],
            'NISSAN' => ['NISSAN'],
            'FORD' => ['FORD'],
            'CHEVROLET' => ['CHEVROLET', 'GM'],
            'VOLKSWAGEN' => ['VOLKSWAGEN'],
            'BMW' => ['BMW'],
            'AUDI' => ['AUDI'],
            'HYUNDAI' => ['HYUNDAI'],
            'KIA' => ['KIA'],
            'LEXUS' => ['LEXUS'],
            'VOLVO' => ['VOLVO'],
            'PORSCHE' => ['PORSCHE'],
            'JEEP' => ['JEEP'],
            'SUBARU' => ['SUBARU'],
            'MITSUBISHI' => ['MITSUBISHI'],
            'ACURA' => ['ACURA'],
            'INFINITI' => ['INFINITI'],
            'DODGE' => ['DODGE'],
            'CHRYSLER' => ['CHRYSLER'],
            'RAM' => ['RAM'],
            'GMC' => ['GMC'],
            'BUICK' => ['BUICK'],
            'CADILLAC' => ['CADILLAC'],
            'LINCOLN' => ['LINCOLN'],
            'FIAT' => ['FIAT'],
            'RENAULT' => ['RENAULT'],
            'PEUGEOT' => ['PEUGEOT'],
            'CITROEN' => ['CITROEN'],
            'SEAT' => ['SEAT'],
            'SKODA' => ['SKODA'],
            'SUZUKI' => ['SUZUKI'],
            'ISUZU' => ['ISUZU'],
            'MG' => ['MG'],
        ];
        
        return $alternatives[$makeUpper] ?? [$makeUpper];
    }

    /**
     * Obtener modelos para una marca y año específicos
     */
    public function getModels($make, $year)
    {
        $cacheKey = "nhtsa_vehicle_models_" . md5("{$make}_{$year}");
        
        return Cache::remember($cacheKey, 604800, function () use ($make, $year) {
            Log::info("Fetching models for make: {$make}, year: {$year}");
            
            // Convertir a mayúsculas para la API NHTSA
            $makeForApi = strtoupper($make);
            
            // Primero intentar con el nombre en mayúsculas
            $response = $this->makeNhtsaRequest("{$this->nhtsaBaseUrl}/GetModelsForMakeYear/make/{$makeForApi}/modelyear/{$year}?format=json");
            
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
                        Log::info("Successfully processed models for {$makeForApi} {$year}", [
                            'count' => count($models)
                        ]);
                        
                        return response()->json([
                            'success' => true,
                            'data' => $models,
                            'source' => 'nhtsa',
                            'count' => count($models)
                        ]);
                    }
                }
            }
            
            // Si falla, intentar con nombres alternativos
            $alternativeMakes = $this->getAlternativeMakeNames($make);
            
            foreach ($alternativeMakes as $alternativeMake) {
                $response = $this->makeNhtsaRequest("{$this->nhtsaBaseUrl}/GetModelsForMakeYear/make/{$alternativeMake}/modelyear/{$year}?format=json");
                
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
                            Log::info("Successfully processed models using alternative name", [
                                'original' => $make,
                                'alternative' => $alternativeMake,
                                'count' => count($models)
                            ]);
                            
                            return response()->json([
                                'success' => true,
                                'data' => $models,
                                'source' => 'nhtsa_alternative',
                                'count' => count($models)
                            ]);
                        }
                    }
                }
            }
            
            // Si todo falla, usar datos de respaldo
            Log::warning("All attempts failed for models: {$make} {$year}, using backup data");
            return $this->getBackupModels($make);
        });
    }

    /**
     * Búsqueda de vehículos por término
     */
    public function searchVehicles($searchTerm)
    {
        $cacheKey = "nhtsa_vehicle_search_" . md5($searchTerm);
        
        return Cache::remember($cacheKey, 3600, function () use ($searchTerm) {
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
                        'source' => 'nhtsa'
                    ]);
                }
            }
            
            return response()->json([
                'success' => true,
                'data' => [],
                'source' => 'backup'
            ]);
        });
    }

    /**
     * Endpoint para verificar el estado de la API
     */
    public function getApiStatus()
    {
        try {
            $response = $this->makeNhtsaRequest("{$this->nhtsaBaseUrl}/getallmakes?format=json");
            
            $status = 'offline';
            $message = 'NHTSA API is not accessible';
            $environment = app()->environment();
            $sslVerification = !(app()->environment('local') || app()->environment('development'));
            
            if ($response) {
                $status = $response->successful() ? 'online' : 'offline';
                $message = $response->successful() ? 'NHTSA API is accessible' : 'NHTSA API returned error';
            }
            
            return response()->json([
                'success' => true,
                'status' => $status,
                'environment' => $environment,
                'ssl_verification' => $sslVerification,
                'message' => $message
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 'offline',
                'environment' => app()->environment(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Datos de respaldo para marcas (YA FILTRADO para México)
     */
    private function getBackupMakes()
    {
        $makes = $this->getPriorityMexicoMakes();
        
        return response()->json([
            'success' => true,
            'data' => $makes,
            'source' => 'backup_filtered',
            'count' => count($makes),
            'note' => 'Marcas prioritarias para mercado mexicano'
        ]);
    }

    /**
     * Datos de respaldo para años
     */
    private function getBackupYears()
    {
        $currentYear = date('Y');
        $years = range($currentYear, $currentYear - 20);
        $years = array_map('strval', $years);
        
        return response()->json([
            'success' => true,
            'data' => $years,
            'source' => 'backup',
            'count' => count($years)
        ]);
    }

    /**
     * Datos de respaldo para modelos
     */
        /**
     * Datos de respaldo para modelos (continuación)
     */
    private function getBackupModels($make)
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
            "JAC" => ["J2", "J3", "J4", "J5", "J6", "T6"]
        ];
        
        $models = $backupModels[$make] ?? [];
        sort($models);
        
        return response()->json([
            'success' => true,
            'data' => $models,
            'source' => 'backup',
            'count' => count($models)
        ]);
    }

    /**
     * Endpoint para forzar la actualización del cache
     */
    public function refreshCache()
    {
        try {
            Cache::forget('nhtsa_vehicle_makes_mexico');
            
            // Limpiar caches de años y modelos populares
            $popularMakes = ['NISSAN', 'TOYOTA', 'HONDA', 'FORD', 'CHEVROLET', 'VOLKSWAGEN', 'BMW', 'MERCEDES-BENZ', 'AUDI'];
            foreach ($popularMakes as $make) {
                Cache::forget("nhtsa_vehicle_years_" . md5($make));
                Cache::forget("nhtsa_vehicle_models_" . md5("{$make}_2024"));
            }
            
            Log::info('Vehicle cache refreshed successfully');
            
            return response()->json([
                'success' => true,
                'message' => 'Cache refreshed successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error refreshing vehicle cache: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error refreshing cache'
            ]);
        }
    }

    /**
     * Endpoint para obtener información del sistema
     */
    public function getSystemInfo()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'environment' => app()->environment(),
                'ssl_verification' => !(app()->environment('local') || app()->environment('development')),
                'cache_driver' => config('cache.default'),
                'cache_ttl_makes' => '1 semana',
                'cache_ttl_years_models' => '1 semana',
                'api_base_url' => $this->nhtsaBaseUrl,
                'mexico_filter' => 'active',
                'filter_type' => 'popular_brands_mexico',
                'brands_count' => count($this->getPriorityMexicoMakes())
            ]
        ]);
    }
}