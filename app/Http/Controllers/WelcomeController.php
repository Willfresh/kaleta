<?php

namespace App\Http\Controllers;

use App\Models\Dish;
use App\Models\DailyMenu;
use App\Models\Reservation;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;


class WelcomeController extends Controller
{
    public function index(): Response
    {
        // 1. Récupérer le jour actuel en français (ex: "lundi")
        $today = now()->locale('fr')->dayName;

        // 2. Récupérer les plats programmés pour aujourd'hui
        $dailyMenuDishes = Dish::whereHas('dailyMenus', function ($query) use ($today) {
            $query->where('day_of_week', $today);
        })->get();

        // 3. Récupérer tous les plats par catégorie (pour la vitrine générale)
        $allDishes = Dish::where('is_available', true)->get();

        // 4. Envoyer les données à la page Welcome.vue via Inertia
        return Inertia::render('Welcome', [
            'today' => ucfirst($today),
            'dailyMenu' => $dailyMenuDishes,
            'allDishes' => $allDishes,
        ]);
    }

    public function storeReservation(Request $request)
    {
        // 1. Validation stricte des données reçues
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'guests' => 'required|integer|min:1|max:20',
            'date' => 'required|date|after_or_equal:today',
            'time' => 'required',
        ]);

        // 2. Création de la réservation en base de données
        Reservation::create($validated);

        // 3. Redirection vers la page d'accueil avec un message de succès
        return redirect()->back()->with('success', 'Votre réservation a bien été enregistrée !');
    }
    public function storeOrder(Request $request)
    {
        // 1. Validation de la commande
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:50',
            'delivery_address' => 'required|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.dish_id' => 'required|exists:dishes,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
        ]);

        // 2. Enregistrement transactionnel sécurisé
        DB::transaction(function () use ($validated) {
            $totalAmount = 0;

            // Calculer le total réel côté serveur pour des raisons de sécurité
            foreach ($validated['items'] as $item) {
                $totalAmount += $item['price'] * $item['quantity'];
            }

            // Créer l'entête de la commande
            $order = Order::create([
                'customer_name' => $validated['customer_name'],
                'customer_phone' => $validated['customer_phone'],
                'delivery_address' => $validated['delivery_address'],
                'total_amount' => $totalAmount,
                'status' => 'en_attente',
            ]);

            // Créer chaque ligne d'article de la commande
            foreach ($validated['items'] as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'dish_id' => $item['dish_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                ]);
            }
        });

        return redirect()->back()->with('success', 'Votre commande a bien été reçue et est en cours de préparation !');
    }
}
