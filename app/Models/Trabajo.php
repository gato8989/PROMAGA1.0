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
        'subtrabajos_usuario', // Asegúrate de agregar este campo
        'subtrabajos_seleccionados',
        'fecha_ingreso',
        'color',
        'completado',
        'notas'
    ];

    protected $casts = [
        'trabajos' => 'array',
        'subtrabajos_estado' => 'array',
        'subtrabajos_usuario' => 'array', // Este cast es crucial
        'subtrabajos_seleccionados' => 'array',
        'completado' => 'boolean'
    ];

    protected $attributes = [
        'subtrabajos_estado' => '{}',
        'subtrabajos_usuario' => '{}', // Agregar valor por defecto
        'subtrabajos_seleccionados' => '{}',
        'color' => '#261472',
        'completado' => false
    ];


    /**
     * Obtener trabajos activos (no completados)
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