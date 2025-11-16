<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RoleSeeder extends Seeder
{
    public function run()
    {
        // Crear roles
        $adminRole = Role::create([
            'name' => 'admin',
            'description' => 'Administrador del sistema'
        ]);

        $tecnicoRole = Role::create([
            'name' => 'tecnico', 
            'description' => 'Técnico'
        ]);

        $monitorRole = Role::create([
            'name' => 'monitor', 
            'description' => 'Monitor'
        ]);

        // Crear usuario admin
        User::create([
            'name' => 'Administrador',
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'role_id' => $adminRole->id
        ]);

        // Crear usuario técnico
        User::create([
            'name' => 'Técnico Ejemplo',
            'email' => 'tecnico@example.com', 
            'password' => Hash::make('password123'),
            'role_id' => $tecnicoRole->id
        ]);

        User::create([
            'name' => 'Monitor Ejemplo',
            'email' => 'monitor@example.com', 
            'password' => Hash::make('password123'),
            'role_id' => $monitorRole->id
        ]);
    }
}