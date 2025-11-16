<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Hash;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Relación con el modelo Role
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Verificar si el usuario es administrador - CORREGIDO
     */
    public function isAdmin(): bool
    {
        // Verificar primero si existe la relación role
        if ($this->relationLoaded('role') && $this->role) {
            return $this->role->name === 'admin';
        }
        
        // Si no hay relación cargada, verificar solo por ID
        return $this->role_id === 1;
    }

    /**
     * Verificar si el usuario es técnico - CORREGIDO
     */
    public function isTecnico(): bool
    {
        // Verificar primero si existe la relación role
        if ($this->relationLoaded('role') && $this->role) {
            return $this->role->name === 'tecnico';
        }
        
        // Si no hay relación cargada, verificar solo por ID
        return $this->role_id === 2;
    }

    /**
     * Verificar si el usuario es monitor - NUEVO MÉTODO
     */
    public function isMonitor(): bool
    {
        // Verificar primero si existe la relación role
        if ($this->relationLoaded('role') && $this->role) {
            return $this->role->name === 'monitor';
        }
        
        // Si no hay relación cargada, verificar solo por ID (asumiendo que monitor es ID 3)
        return $this->role_id === 3;
    }

    /**
     * Verificar si el usuario puede terminar trabajos - NUEVO MÉTODO
     */
    public function canTerminarTrabajos(): bool
    {
        return $this->isAdmin() || $this->isTecnico();
    }

    /**
     * Scope para buscar por email
     */
    public function scopeByEmail($query, $email)
    {
        return $query->where('email', $email);
    }

    /**
     * Crear un nuevo usuario
     */
    public static function createUser(array $data): self
    {
        return self::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role_id' => $data['role_id'] ?? 2 // Default a técnico
        ]);
    }

    /**
     * Actualizar contraseña
     */
    public function updatePassword(string $newPassword): bool
    {
        $this->password = Hash::make($newPassword);
        return $this->save();
    }

    /**
     * Verificar contraseña
     */
    public function checkPassword(string $password): bool
    {
        return Hash::check($password, $this->password);
    }

    /**
     * Obtener todos los tokens del usuario
     */
    public function getTokens()
    {
        return $this->tokens;
    }

    /**
     * Revocar todos los tokens del usuario
     */
    public function revokeAllTokens(): void
    {
        $this->tokens()->delete();
    }
}