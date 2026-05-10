<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppApiService
{
    protected string $baseUrl;
    protected string $apiKey;

    public function __construct()
    {
        $this->baseUrl = env('WHATSAPP_API_URL', 'http://localhost:8080');
        $this->apiKey = env('WHATSAPP_API_KEY', 'PM5-SuperSecret-Key-2026');
    }

    protected function client()
    {
        return Http::withHeaders([
            'apikey' => $this->apiKey,
            'Content-Type' => 'application/json',
        ]);
    }

    /**
     * Verificar estado de la conexión
     */
    public function getStatus(): array
    {
        try {
            $response = $this->client()->get("{$this->baseUrl}/status");
            $data = $response->json();

            return [
                'success' => true,
                'connected' => $data['connected'] ?? false,
                'number' => $data['number'] ?? null,
                'data' => $data,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'connected' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Enviar PDF como base64 directamente
     */
    public function sendPdfBase64(string $phone, string $pdfBase64, string $fileName, string $caption = ''): array
    {
        try {
            $response = $this->client()->post("{$this->baseUrl}/send-document", [
                'number' => $this->formatPhone($phone),
                'document' => $pdfBase64,
                'fileName' => $fileName,
                'caption' => $caption,
            ]);

            $data = $response->json();

            Log::info('WhatsApp: PDF enviado (base64)', [
                'to' => $phone,
                'success' => $data['success'] ?? false,
            ]);

            return [
                'success' => $data['success'] ?? $response->successful(),
                'data' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp: Error enviando PDF base64', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
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
            Log::error('WhatsApp logout error:', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Forzar cierre de sesión (método alternativo)
     */
    public function forceLogout(): array
    {
        try {
            $response = $this->client()->delete("{$this->baseUrl}/logout");
            return ['success' => true, 'data' => $response->json()];
        } catch (\Exception $e) {
            Log::error('WhatsApp force logout error:', ['error' => $e->getMessage()]);
            throw $e;
        }
    }


    /**
     * Obtener QR para escanear
     */
    public function getQrCode(): array
    {
        try {
            $response = $this->client()->get("{$this->baseUrl}/qr");
            $data = $response->json();

            return [
                'success' => true,
                'connected' => $data['connected'] ?? false,
                'qrcode' => $data['qrcode'] ?? null,
                'message' => $data['message'] ?? null,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Enviar mensaje de texto
     */
    public function sendMessage(string $phone, string $message): array
    {
        try {
            $response = $this->client()->post("{$this->baseUrl}/send-text", [
                'number' => $this->formatPhone($phone),
                'text' => $message,
            ]);

            $data = $response->json();

            Log::info('WhatsApp: Mensaje enviado', [
                'to' => $phone,
                'success' => $data['success'] ?? false,
            ]);

            return [
                'success' => $data['success'] ?? $response->successful(),
                'data' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp: Error enviando mensaje', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Enviar PDF como documento
     */
    public function sendPdf(string $phone, string $pdfPath, string $caption = ''): array
    {
        try {
            $pdfBase64 = base64_encode(file_get_contents($pdfPath));
            $fileName = basename($pdfPath);

            $response = $this->client()->post("{$this->baseUrl}/send-document", [
                'number' => $this->formatPhone($phone),
                'document' => $pdfBase64,
                'fileName' => $fileName,
                'caption' => $caption,
            ]);

            $data = $response->json();

            Log::info('WhatsApp: PDF enviado', [
                'to' => $phone,
                'success' => $data['success'] ?? false,
            ]);

            return [
                'success' => $data['success'] ?? $response->successful(),
                'data' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp: Error enviando PDF', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Formatear número de teléfono
     */
    protected function formatPhone(string $phone): string
    {
        return preg_replace('/[^0-9]/', '', $phone);
    }
}