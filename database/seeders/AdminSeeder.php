<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Crée le premier compte administrateur de KALETA.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@kaleta.com'], // Si cet email existe déjà, Laravel ne le recréera pas (évite les doublons)
            [
                'name' => 'Gérant KALETA',
                'password' => Hash::make('AdminKaleta2026'), // CHANGEZ CE MOT DE PASSE EN PRODUCTION !
            ]
        );
    }
}
