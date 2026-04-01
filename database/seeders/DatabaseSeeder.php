<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('--- DEBUT DU MEGA-SEED (Stress Test 75k+) ---');

        // ==========================================
        // 1. CRÉATION DES UTILISATEURS
        // ==========================================
        $this->command->info('Création des utilisateurs...');

        // Compte ADMIN
        $admin = User::updateOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Administrateur DTTIA',
                'password' => Hash::make('Alasco@72'),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );
        $this->command->info('✓ Admin créé: admin@gmail.com / Admin@2026');

        // Compte USER (magasinier)
        $user = User::updateOrCreate(
            ['email' => 'al@al.com'],
            [
                'name' => 'Utilisateur Magasin',
                'password' => Hash::make('Alasco@72'),
                'role' => 'user',
                'code_materiel' => 'USER' . Str::random(6),
                'email_verified_at' => now(),
            ]
        );
        $this->command->info('✓ Utilisateur créé: al@al.com / User@2026');


        $this->command->info('--- TOUTES LES DONNÉES SONT EN PLACE ! ---');
    }
}
