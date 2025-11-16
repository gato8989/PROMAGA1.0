<?php
// app/Models/HistorialTrabajo.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistorialTrabajo extends Model
{
    use HasFactory;

    protected $fillable = [
        'marca',
        'modelo',
        'año',
        'trabajos',
        'subtrabajos_estado',
        'subtrabajos_seleccionados',
        'fecha_ingreso',
        'fecha_terminado',
        'hora_terminado', // NUEVO
        'hora_creacion', // NUEVO
        'usuario_termino',
        'color',
        'notas'
    ];

    protected $casts = [
        'trabajos' => 'array',
        'subtrabajos_estado' => 'array',
        'subtrabajos_seleccionados' => 'array' // NUEVO CAST
    ];

    /**
     * Scope para buscar por marca
     */
    public function scopePorMarca($query, $marca)
    {
        return $query->where('marca', 'like', "%{$marca}%");
    }

    /**
     * Scope para buscar por modelo
     */
    public function scopePorModelo($query, $modelo)
    {
        return $query->where('modelo', 'like', "%{$modelo}%");
    }

    /**
     * Scope para buscar por fecha
     */
    public function scopePorFecha($query, $fecha)
    {
        return $query->where('fecha_terminado', $fecha);
    }

    /**
     * Scope para buscar por rango de fechas
     */
    public function scopePorRangoFechas($query, $fechaInicio, $fechaFin)
    {
        return $query->whereBetween('fecha_terminado', [$fechaInicio, $fechaFin]);
    }

    /**
     * Scope para búsqueda general
     */
    public function scopeBuscar($query, $termino)
    {
        return $query->where('marca', 'like', "%{$termino}%")
                    ->orWhere('modelo', 'like', "%{$termino}%")
                    ->orWhere('año', 'like', "%{$termino}%");
    }
}