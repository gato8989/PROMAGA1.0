<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description'];

    /**
     * Relación con usuarios
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Scope para buscar por nombre
     */
    public function scopeByName($query, $name)
    {
        return $query->where('name', $name);
    }

    /**
     * Obtener rol por nombre
     */
    public static function getByName(string $name): ?self
    {
        return self::where('name', $name)->first();
    }

    /**
     * Verificar si el rol es administrador
     */
    public function isAdmin(): bool
    {
        return $this->name === 'admin';
    }

    /**
     * Verificar si el rol es técnico
     */
    public function isTecnico(): bool
    {
        return $this->name === 'tecnico';
    }

    /**
     * Verificar si el rol es monitor - NUEVO MÉTODO
     */
    public function isMonitor(): bool
    {
        return $this->name === 'monitor';
    }

    /**
     * Verificar si el rol puede terminar trabajos (técnico y admin sí, monitor no)
     */
    public function canTerminarTrabajos(): bool
    {
        return $this->isAdmin() || $this->isTecnico();
    }
}