<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Cliente extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'telefono',
        'historial_trabajo_id',
        'ultima_visita',
        'recordatorio_enviado',
        'ultimo_recordatorio',
        'finalizacion_enviada',
        'garantia_enviada',
        'recomendacion_enviada',
    ];

    protected $casts = [
        'ultima_visita' => 'datetime',
        'ultimo_recordatorio' => 'datetime',
        'recordatorio_enviado' => 'boolean',
        'finalizacion_enviada' => 'boolean',
        'garantia_enviada' => 'boolean',
        'recomendacion_enviada' => 'boolean',
        'created_at' => 'datetime',
    ];

    /**
     * Relación con el trabajo del historial
     */
    public function historialTrabajo()
    {
        return $this->belongsTo(HistorialTrabajo::class, 'historial_trabajo_id');
    }

    /**
     * Verificar si han pasado 6 meses desde la última visita
     */
    public function hanPasado6Meses(): bool
    {
        if (!$this->ultima_visita) {
            return false;
        }

        return $this->ultima_visita->diffInMonths(now()) >= 6;
    }

    /**
     * Verificar si se puede enviar recordatorio (6 meses)
     */
    public function puedeEnviarRecordatorio(): bool
    {
        if (!$this->ultima_visita) {
            return false;
        }

        // Verificar si han pasado 6 meses desde la última visita
        if ($this->ultima_visita->diffInMonths(now()) < 6) {
            return false;
        }

        // Si ya se envió un recordatorio alguna vez
        if ($this->recordatorio_enviado && $this->ultimo_recordatorio) {
            // Verificar si han pasado 6 meses desde el último recordatorio
            return $this->ultimo_recordatorio->diffInMonths(now()) >= 6;
        }

        return true;
    }

    /**
     * Verificar si se puede enviar recomendación (1 día después de creado)
     */
    public function puedeEnviarRecomendacion(): bool
    {
        // Debe tener un trabajo asignado
        if (!$this->historial_trabajo_id) {
            return false;
        }

        // Si ya se envió recomendación, no enviar de nuevo
        if ($this->recomendacion_enviada) {
            return false;
        }

        // Debe haber pasado al menos 1 día desde la creación del cliente
        $diasTranscurridos = $this->created_at->diffInDays(now());
        
        // Para depuración - puedes eliminar después
        \Illuminate\Support\Facades\Log::info('Verificando recomendación', [
            'cliente_id' => $this->id,
            'creado' => $this->created_at->format('Y-m-d H:i:s'),
            'ahora' => now()->format('Y-m-d H:i:s'),
            'dias_transcurridos' => $diasTranscurridos,
            'puede_enviar' => $diasTranscurridos >= 1
        ]);
        
        return $diasTranscurridos >= 1;
    }

    /**
     * Obtener días restantes para recordatorio
     */
    public function getDiasParaRecordatorio(): ?int
    {
        if (!$this->ultima_visita) {
            return null;
        }

        $fechaRecordatorio = $this->ultima_visita->copy()->addMonths(6);
        return (int) now()->diffInDays($fechaRecordatorio, false);
    }

    /**
     * Obtener días restantes para recomendación
     */
    public function getDiasParaRecomendacion(): ?int
    {
        if ($this->recomendacion_enviada || !$this->historial_trabajo_id) {
            return null;
        }

        $fechaRecomendacion = $this->created_at->copy()->addDay();
        $diasRestantes = (int) now()->diffInDays($fechaRecomendacion, false);
        
        // Si es 0 o negativo, significa que ya pasó el día
        return $diasRestantes;
    }
    /**
     * Scope para buscar
     */
    public function scopeBuscar($query, $termino)
    {
        return $query->where('nombre', 'like', "%{$termino}%")
                    ->orWhere('telefono', 'like', "%{$termino}%");
    }
}