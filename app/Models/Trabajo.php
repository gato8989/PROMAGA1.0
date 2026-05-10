<?php
// app/Models/Trabajo.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trabajo extends Model
{
    use HasFactory;

    protected $fillable = [
        'marca',
        'modelo',
        'año',
        'trabajos',
        'subtrabajos_estado',
        'subtrabajos_usuario', 
        'subtrabajos_seleccionados',
        'fecha_ingreso',
        'color',
        'completado',
        'notas',
        'cliente_nombre',      // ← NUEVO CAMPO
        'cliente_telefono'     // ← NUEVO CAMPO
    ];

    protected $casts = [
        'trabajos' => 'array',
        'subtrabajos_estado' => 'array',
        'subtrabajos_usuario' => 'array', 
        'subtrabajos_seleccionados' => 'array',
        'completado' => 'boolean'
    ];

    protected $attributes = [
        'subtrabajos_estado' => '{}',
        'subtrabajos_usuario' => '{}',
        'subtrabajos_seleccionados' => '{}',
        'color' => '#261472',
        'completado' => false
    ];

    /**
     * Obtener trabajos activos 
     */
    public static function getActivos()
    {
        return self::where('completado', false)->get();
    }

    /**
     * Marcar trabajo como completado
     */
    public function marcarCompletado()
    {
        $this->update(['completado' => true]);
    }

    /**
     * Actualizar estado de un subtrabajo
     */
    public function actualizarSubtrabajoEstado($subtrabajo, $estado)
    {
        $estados = $this->subtrabajos_estado ?? [];
        $estados[$subtrabajo] = $estado;
        $this->update(['subtrabajos_estado' => $estados]);
    }

    /**
     * Obtener el estado de un subtrabajo específico
     */
    public function obtenerEstadoSubtrabajo($subtrabajo)
    {
        return $this->subtrabajos_estado[$subtrabajo] ?? false;
    }
}