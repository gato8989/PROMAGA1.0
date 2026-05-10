<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\HistorialTrabajo;
use App\Services\WhatsAppApiService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ClienteController extends Controller
{
    protected WhatsAppApiService $whatsapp;

    public function __construct(WhatsAppApiService $whatsapp)
    {
        $this->whatsapp = $whatsapp;
    }

    public function index(Request $request)
    {
        try {
            $query = Cliente::with('historialTrabajo');

            if ($request->has('busqueda') && $request->busqueda) {
                $termino = $request->busqueda;
                $query->where(function ($q) use ($termino) {
                    $q->where('nombre', 'like', "%{$termino}%")
                    ->orWhere('telefono', 'like', "%{$termino}%");
                });
            }

            $clientes = $query->orderBy('created_at', 'desc')->get();

            $clientes->each(function ($cliente) {
                $cliente->puede_recordatorio = $cliente->puedeEnviarRecordatorio();
                $cliente->puede_recomendacion = $cliente->puedeEnviarRecomendacion();
                $cliente->dias_para_recordatorio = $cliente->getDiasParaRecordatorio();
                $cliente->dias_para_recomendacion = $cliente->getDiasParaRecomendacion();
                
                // Log para depuración - puedes eliminar después
                \Illuminate\Support\Facades\Log::info('Cliente cargado', [
                    'id' => $cliente->id,
                    'nombre' => $cliente->nombre,
                    'created_at' => $cliente->created_at?->format('Y-m-d H:i:s'),
                    'puede_recomendacion' => $cliente->puede_recomendacion,
                    'dias_para_recomendacion' => $cliente->dias_para_recomendacion
                ]);
            });

            return response()->json([
                'success' => true,
                'data' => $clientes,
            ]);
        } catch (\Exception $e) {
            Log::error('ClienteController@index:', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Error al cargar clientes'], 500);
        }
    }

    /**
     * Registrar nuevo cliente
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'nombre' => 'required|string|max:255',
                'telefono' => 'required|string|max:20',
                'historial_trabajo_id' => 'nullable|exists:historial_trabajos,id',
            ]);

            if ($request->historial_trabajo_id) {
                $trabajo = HistorialTrabajo::find($request->historial_trabajo_id);
                if ($trabajo && $trabajo->fecha_terminado) {
                    $validated['ultima_visita'] = Carbon::createFromFormat('d/m/Y', $trabajo->fecha_terminado);
                }
            }

            $cliente = Cliente::create($validated);

            Log::info('Cliente creado', ['id' => $cliente->id, 'nombre' => $cliente->nombre]);

            return response()->json([
                'success' => true,
                'data' => $cliente->load('historialTrabajo'),
                'message' => 'Cliente registrado exitosamente',
            ], 201);
        } catch (\Exception $e) {
            Log::error('ClienteController@store:', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Error al crear cliente'], 500);
        }
    }


    /**
     * Cerrar sesión de WhatsApp
     */
    public function logout(): array
    {
        try {
            $response = $this->client()->post("{$this->baseUrl}/logout");
            return ['success' => true, 'data' => $response->json()];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }


    /**
     * Desconectar WhatsApp
     */
    public function whatsappLogout()
    {
        try {
            $result = $this->whatsapp->logout();

            return response()->json([
                'success' => true,
                'message' => 'WhatsApp desconectado',
            ]);
        } catch (\Exception $e) {
            Log::error('Error desconectando WhatsApp:', ['error' => $e->getMessage()]);
            
            // Intentar con el endpoint alternativo
            try {
                $result = $this->whatsapp->forceLogout();
                return response()->json([
                    'success' => true,
                    'message' => 'WhatsApp desconectado (force)',
                ]);
            } catch (\Exception $e2) {
                return response()->json([
                    'success' => false, 
                    'error' => $e->getMessage()
                ], 500);
            }
        }
    }

    /**
     * Actualizar cliente
     */
    public function update(Request $request, Cliente $cliente)
    {
        try {
            $validated = $request->validate([
                'nombre' => 'sometimes|string|max:255',
                'telefono' => 'sometimes|string|max:20',
                'historial_trabajo_id' => 'nullable|exists:historial_trabajos,id',
            ]);

            if ($request->has('historial_trabajo_id') && $request->historial_trabajo_id) {
                $trabajo = HistorialTrabajo::find($request->historial_trabajo_id);
                if ($trabajo && $trabajo->fecha_terminado) {
                    $validated['ultima_visita'] = Carbon::createFromFormat('d/m/Y', $trabajo->fecha_terminado);
                }
            }

            $cliente->update($validated);

            return response()->json([
                'success' => true,
                'data' => $cliente->fresh()->load('historialTrabajo'),
                'message' => 'Cliente actualizado exitosamente',
            ]);
        } catch (\Exception $e) {
            Log::error('ClienteController@update:', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Error al actualizar cliente'], 500);
        }
    }



    /**
     * Enviar recomendación de Google Maps
     */
    public function enviarRecomendacion(Cliente $cliente)
    {
        try {
            // Validar si puede enviar recomendación
            if (!$cliente->puedeEnviarRecomendacion()) {
                $dias = $cliente->getDiasParaRecomendacion();
                
                if ($dias !== null && $dias > 0) {
                    $mensajeError = "Deben pasar {$dias} día(s) para enviar recomendación";
                } elseif (!$cliente->historial_trabajo_id) {
                    $mensajeError = "El cliente debe tener un vehículo asignado";
                } elseif ($cliente->recomendacion_enviada) {
                    $mensajeError = "La recomendación ya fue enviada anteriormente";
                } else {
                    $mensajeError = "Aún no puede enviar la recomendación. Debe esperar 1 día después de registrar el cliente";
                }
                
                return response()->json([
                    'success' => false,
                    'error' => $mensajeError,
                ], 400);
            }

            $trabajo = $cliente->historialTrabajo;

            if (!$trabajo) {
                return response()->json([
                    'success' => false,
                    'error' => 'No hay un trabajo asignado a este cliente',
                ], 400);
            }

            $urlGoogleMaps = 'https://share.google/h0nBrWic12nJndy5K';

            $mensaje = "⭐ *¡Recomiéndanos!* ⭐\n\n"
                . "Hola *{$cliente->nombre}*,\n\n"
                . "Gracias por confiar en Automotriz ProMaga para el servicio de su vehículo:\n"
                . "🚗 *{$trabajo->marca} {$trabajo->modelo} {$trabajo->año}*\n\n"
                . "Nos encantaría conocer su opinión. ¿Podría dejarnos una reseña en Google Maps?\n\n"
                . "👉 {$urlGoogleMaps}\n\n"
                . "¡Su opinión nos ayuda a mejorar! 🛠️\n\n"
                . "Gracias,\n"
                . "Automotriz ProMaga";

            $resultado = $this->whatsapp->sendMessage($cliente->telefono, $mensaje);

            if ($resultado['success']) {
                $cliente->update(['recomendacion_enviada' => true]);
                
                Log::info('Recomendación enviada', [
                    'cliente_id' => $cliente->id,
                    'nombre' => $cliente->nombre,
                    'dias_despues_creacion' => $cliente->created_at->diffInDays(now())
                ]);
            }

            return response()->json([
                'success' => $resultado['success'],
                'message' => $resultado['success'] ? '⭐ Recomendación enviada' : '❌ Error al enviar',
            ]);
        } catch (\Exception $e) {
            Log::error('Error enviando recomendación:', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Eliminar cliente
     */
    public function destroy(Cliente $cliente)
    {
        try {
            $cliente->delete();
            return response()->json(['success' => true, 'message' => 'Cliente eliminado exitosamente']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => 'Error al eliminar cliente'], 500);
        }
    }


    /**
     * Enviar recordatorio de mantenimiento
     */
    public function enviarRecordatorio(Cliente $cliente)
    {
        try {
            // Validar si puede enviar recordatorio
            if (!$cliente->puedeEnviarRecordatorio()) {
                $dias = $cliente->getDiasParaRecordatorio();
                $mensajeError = $dias && $dias > 0 
                    ? "Deben pasar {$dias} días más para enviar recordatorio" 
                    : 'Aún no han pasado 6 meses desde la última visita';
                
                return response()->json([
                    'success' => false,
                    'error' => $mensajeError,
                ], 400);
            }

            $trabajo = $cliente->historialTrabajo;
            $vehiculo = $trabajo
                ? "{$trabajo->marca} {$trabajo->modelo} {$trabajo->año}"
                : 'su vehículo';

            $fechaUltima = $cliente->ultima_visita
                ? $cliente->ultima_visita->format('d/m/Y')
                : 'No registrada';

            $mensaje = "🔧 *Recordatorio de Mantenimiento* 🔧\n\n"
                . "Hola *{$cliente->nombre}*,\n\n"
                . "Han pasado más de 6 meses desde su último servicio.\n\n"
                . "🚗 Vehículo: *{$vehiculo}*\n"
                . "📅 Última visita: *{$fechaUltima}*\n\n"
                . "Le recomendamos agendar una cita.\n\n"
                . "📍 Automotriz ProMaga\n"
                . "¡Esperamos verte pronto! 🛠️";

            $resultado = $this->whatsapp->sendMessage($cliente->telefono, $mensaje);

            if ($resultado['success']) {
                $cliente->update([
                    'recordatorio_enviado' => true,
                    'ultimo_recordatorio' => now(),
                    // No cambiamos ultima_visita para mantener el historial real
                ]);
                
                Log::info('Recordatorio enviado', [
                    'cliente_id' => $cliente->id,
                    'nombre' => $cliente->nombre,
                ]);
            }

            return response()->json([
                'success' => $resultado['success'],
                'message' => $resultado['success'] ? '✅ Recordatorio enviado' : '❌ Error al enviar',
            ]);
        } catch (\Exception $e) {
            Log::error('Error enviando recordatorio:', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Enviar aviso de finalización
     */
    public function enviarFinalizacion(Cliente $cliente)
    {
        try {
            $trabajo = $cliente->historialTrabajo;

            if (!$trabajo) {
                return response()->json([
                    'success' => false,
                    'error' => 'No hay un trabajo asignado',
                ], 400);
            }

            $vehiculo = "{$trabajo->marca} {$trabajo->modelo} {$trabajo->año}";

            $mensaje = "✅ *Servicio Finalizado* ✅\n\n"
                . "Hola *{$cliente->nombre}*,\n\n"
                . "¡Su vehículo está listo!\n\n"
                . "🚗 Vehículo: *{$vehiculo}*\n"
                . "📅 Fecha: *{$trabajo->fecha_terminado}*\n\n"
                . "📍 Automotriz ProMaga\n"
                . "¡Gracias por confiar en nosotros! 🛠️";

            $resultado = $this->whatsapp->sendMessage($cliente->telefono, $mensaje);

            if ($resultado['success']) {
                $cliente->update(['finalizacion_enviada' => true]);
            }

            return response()->json([
                'success' => $resultado['success'],
                'message' => $resultado['success'] ? '✅ Finalización enviada' : '❌ Error al enviar',
            ]);
        } catch (\Exception $e) {
            Log::error('Error enviando finalización:', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Generar y enviar garantía en PDF
     */
    public function generarGarantia(Cliente $cliente)
    {
        try {
            Log::info('=== INICIANDO GENERACIÓN DE GARANTÍA ===');
            Log::info('Cliente ID: ' . $cliente->id . ' | Nombre: ' . $cliente->nombre);
            
            $trabajo = $cliente->historialTrabajo;

            if (!$trabajo) {
                Log::error('GARANTÍA: No hay trabajo asignado');
                return response()->json([
                    'success' => false,
                    'error' => 'No hay un trabajo asignado',
                ], 400);
            }

            Log::info('GARANTÍA: Trabajo encontrado', [
                'trabajo_id' => $trabajo->id,
                'vehiculo' => "{$trabajo->marca} {$trabajo->modelo} {$trabajo->año}"
            ]);

            $datos = [
                'cliente' => $cliente,
                'trabajo' => $trabajo,
                'vehiculo' => "{$trabajo->marca} {$trabajo->modelo} {$trabajo->año}",
                'fecha_emision' => now()->format('d/m/Y'),
                'numero_garantia' => 'GTIA-' . str_pad($cliente->id, 5, '0', STR_PAD_LEFT) . '-' . now()->format('Ym'),
                'dias_garantia' => 30,
            ];

            Log::info('GARANTÍA: Datos preparados', $datos);

            // Verificar que la vista existe
            if (!view()->exists('pdf.garantia')) {
                Log::error('GARANTÍA: Vista pdf.garantia NO EXISTE');
                return response()->json([
                    'success' => false,
                    'error' => 'Vista de PDF no encontrada',
                ], 500);
            }

            Log::info('GARANTÍA: Generando PDF...');
            
            try {
                $pdf = PDF::loadView('pdf.garantia', $datos);
                $pdf->setPaper('A4');
                $pdfOutput = $pdf->output();
                Log::info('GARANTÍA: PDF generado. Tamaño: ' . strlen($pdfOutput) . ' bytes');
            } catch (\Exception $pdfError) {
                Log::error('GARANTÍA: Error al generar PDF', [
                    'error' => $pdfError->getMessage(),
                    'trace' => $pdfError->getTraceAsString()
                ]);
                return response()->json([
                    'success' => false,
                    'error' => 'Error al generar PDF: ' . $pdfError->getMessage(),
                ], 500);
            }

            $fileName = "garantia_{$cliente->id}_{$trabajo->id}_" . time() . ".pdf";
            $filePath = "garantias/{$fileName}";

            Log::info('GARANTÍA: Guardando en storage', ['path' => $filePath]);

            try {
                Storage::disk('public')->put($filePath, $pdfOutput);
                Log::info('GARANTÍA: Archivo guardado exitosamente');
            } catch (\Exception $storageError) {
                Log::error('GARANTÍA: Error al guardar PDF', [
                    'error' => $storageError->getMessage()
                ]);
                return response()->json([
                    'success' => false,
                    'error' => 'Error al guardar PDF: ' . $storageError->getMessage(),
                ], 500);
            }

            $fullPath = storage_path("app/public/{$filePath}");
            
            if (!file_exists($fullPath)) {
                Log::error('GARANTÍA: Archivo NO EXISTE después de guardar', ['path' => $fullPath]);
                return response()->json([
                    'success' => false,
                    'error' => 'Archivo PDF no se generó correctamente',
                ], 500);
            }

            Log::info('GARANTÍA: Archivo existe. Convirtiendo a base64...');
            $pdfBase64 = base64_encode(file_get_contents($fullPath));
            Log::info('GARANTÍA: Base64 generado. Longitud: ' . strlen($pdfBase64));

            $mensaje = "📄 *Garantía de Servicio - Automotriz ProMaga*\n\n"
                . "Estimado(a) *{$cliente->nombre}*,\n\n"
                . "Adjunto el certificado de garantía para:\n"
                . "🚗 *{$datos['vehiculo']}*\n\n"
                . "📋 No. Garantía: *{$datos['numero_garantia']}*\n"
                . "📅 Vigencia: *{$datos['dias_garantia']} días*\n\n"
                . "¡Gracias por su preferencia! 🛠️";

            Log::info('GARANTÍA: Enviando a WhatsApp...', [
                'telefono' => $cliente->telefono,
                'pdf_length' => strlen($pdfBase64)
            ]);

            try {
                $resultado = $this->whatsapp->sendPdfBase64(
                    $cliente->telefono,
                    $pdfBase64,
                    $fileName,
                    $mensaje
                );
                
                Log::info('GARANTÍA: Respuesta de WhatsApp', [
                    'success' => $resultado['success'] ?? false,
                    'response' => $resultado['data'] ?? null,
                    'error' => $resultado['error'] ?? null
                ]);
            } catch (\Exception $waError) {
                Log::error('GARANTÍA: Error al enviar a WhatsApp', [
                    'error' => $waError->getMessage(),
                    'trace' => $waError->getTraceAsString()
                ]);
                return response()->json([
                    'success' => false,
                    'error' => 'Error al enviar WhatsApp: ' . $waError->getMessage(),
                ], 500);
            }

            if ($resultado['success']) {
                $cliente->update(['garantia_enviada' => true]);
                Log::info('GARANTÍA: ✅ Proceso completado exitosamente');
            } else {
                Log::error('GARANTÍA: ❌ WhatsApp reportó error', $resultado);
            }

            return response()->json([
                'success' => $resultado['success'],
                'message' => $resultado['success'] ? '✅ Garantía generada y enviada' : '❌ Error al enviar: ' . ($resultado['error'] ?? 'Desconocido'),
                'pdf_url' => asset("storage/{$filePath}"),
                'numero_garantia' => $datos['numero_garantia'],
                'debug' => [
                    'pdf_created' => true,
                    'pdf_size' => strlen($pdfBase64),
                    'wa_response' => $resultado,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('GARANTÍA: Error GENERAL', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Error general: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Estado de WhatsApp (incluye QR)
     */
    public function whatsappStatus()
    {
        try {
            $status = $this->whatsapp->getStatus();

            if ($status['connected']) {
                return response()->json([
                    'success' => true,
                    'connected' => true,
                    'number' => $status['number'],
                ]);
            }

            // Si no está conectado, obtener QR
            $qr = $this->whatsapp->getQrCode();

            return response()->json([
                'success' => true,
                'connected' => false,
                'qrcode' => $qr['qrcode'] ?? null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'connected' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Buscar trabajos del historial
     */
    public function buscarTrabajosHistorial(Request $request)
    {
        try {
            $query = HistorialTrabajo::query();

            // Si hay término de búsqueda, filtrar
            if ($request->has('busqueda') && $request->busqueda && trim($request->busqueda) !== '') {
                $termino = trim($request->busqueda);
                $query->where(function ($q) use ($termino) {
                    $q->where('marca', 'like', "%{$termino}%")
                    ->orWhere('modelo', 'like', "%{$termino}%")
                    ->orWhere('año', 'like', "%{$termino}%");
                });
            }
            
            // Siempre ordenar por fecha de terminado descendente (más recientes primero)
            // y limitar a 10 resultados
            $trabajos = $query->select('id', 'marca', 'modelo', 'año', 'fecha_terminado', 'color')
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc')
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true, 
                'data' => $trabajos,
                'is_default_list' => empty($request->busqueda) // Indicar si es la lista por defecto
            ]);
        } catch (\Exception $e) {
            Log::error('ClienteController@buscarTrabajosHistorial:', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Calcular días para próximo recordatorio
     */
    protected function calcularDiasRecordatorio(Cliente $cliente): ?int
    {
        return $cliente->getDiasParaRecordatorio();
    }
}