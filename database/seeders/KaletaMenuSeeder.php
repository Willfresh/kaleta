<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Dish;
use App\Models\DailyMenu;

class KaletaMenuSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Insertion de quelques plats et boissons de KALETA
        $rizPoulet = Dish::create([
            'name' => 'Riz blanc + sauce tomate + poulet',
            'price' => 3000,
            'category' => 'Plats',
        ]);

        $akoume = Dish::create([
            'name' => 'Akoumé + adémè + poisson frit',
            'price' => 2500,
            'category' => 'Plats',
        ]);

        $alloco = Dish::create([
            'name' => 'Alloco + demi poisson braisé',
            'price' => 2000,
            'category' => 'Plats',
        ]);

        $sunset = Dish::create([
            'name' => 'Kaleta Sunset',
            'description' => 'Rhum, mangue, ananas, orange, grenadine',
            'price' => 5000,
            'category' => 'Cocktails',
        ]);

        $ginger = Dish::create([
            'name' => 'Ginger Power',
            'description' => 'Vodka, gingembre frais, citron, miel',
            'price' => 4000,
            'category' => 'Cocktails',
        ]);

        // 2. Association des plats à des jours spécifiques de la semaine (Menu du Jour)
        DailyMenu::create(['day_of_week' => 'lundi', 'dish_id' => $rizPoulet->id]);
        DailyMenu::create(['day_of_week' => 'lundi', 'dish_id' => $akoume->id]);
        DailyMenu::create(['day_of_week' => 'mardi', 'dish_id' => $alloco->id]);
    }
}
