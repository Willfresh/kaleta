<?php

namespace App\Http\Controllers;

use App\Models\Dish;
use App\Models\Reservation;
use App\Models\DailyMenu;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Category; // LIGNE INDISPENSABLE : Importation du modèle Category !
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    /**
     * Affiche la vue principale d'administration.
     */
    public function index(Request $request): Response
    {
        // 1. Récupérer toutes les réservations
        $reservations = Reservation::orderBy('date', 'asc')
            ->orderBy('time', 'asc')
            ->get();

        // 2. Récupérer tous les plats pour les afficher
        $dishes = Dish::orderBy('category', 'asc')->get();

        // 3. Récupérer le planning de la semaine
        $weeklySchedule = DailyMenu::with('dish')->get();

        // 4. Récupérer toutes les commandes (livraisons et sur place)
        $orders = Order::with('items.dish')
            ->orderBy('created_at', 'desc')
            ->get();

        // 5. Récupérer dynamiquement toutes les catégories créées (LIGNE IMPORTANTE)
        $categories = Category::orderBy('name', 'asc')->get();

        // 6. MOTEUR DE RAPPORT COMPTABLE (FILTRE DE DATES)
        $startDate = $request->query('start_date', Carbon::today()->toDateString());
        $endDate = $request->query('end_date', Carbon::today()->toDateString());

        $reportOrders = Order::with('items.dish')
            ->where('status', 'livre')
            ->whereBetween('updated_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay()
            ])
            ->get();

        $reportTotal = $reportOrders->sum('total_amount');

        // 7. Chiffre d'affaires spécifique à AJOURD'HUI
        $todaySales = Order::where('status', 'livre')
            ->whereDate('updated_at', Carbon::today())
            ->sum('total_amount');

        return Inertia::render('Dashboard', [
            'reservations' => $reservations,
            'dishes' => $dishes,
            'weeklySchedule' => $weeklySchedule,
            'orders' => $orders,
            'todaySales' => (float) $todaySales,

            // Rapports
            'reportFilters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'reportOrders' => $reportOrders,
            'reportTotal' => (float) $reportTotal,

            // NOUVEAU : Envoi des catégories réelles de la base de données au frontend !
            'categories' => $categories,
        ]);
    }

    /**
     * Confirmer / Annuler une réservation.
     */
    public function updateReservationStatus(Request $request, Reservation $reservation)
    {
        $validated = $request->validate([
            'status' => 'required|in:en_attente,confirme,annule',
        ]);

        $reservation->update(['status' => $validated['status']]);

        return redirect()->back()->with('success', 'Statut de la réservation mis à jour.');
    }

    /**
     * Enregistrer un plat.
     */
    public function storeDish(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'category' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('dishes', 'public');
        }

        Dish::create([
            'name' => $validated['name'],
            'price' => $validated['price'],
            'category' => $validated['category'],
            'description' => $validated['description'] ?? null,
            'image_path' => $imagePath,
        ]);

        return redirect()->back()->with('success', 'Le plat a été ajouté à la carte.');
    }

    /**
     * Planifier au menu du jour.
     */
    public function storeDailyMenu(Request $request)
    {
        $validated = $request->validate([
            'dish_id' => 'required|exists:dishes,id',
            'day_of_week' => 'required|in:lundi,mardi,mercredi,jeudi,vendredi,samedi,dimanche',
        ]);

        DailyMenu::firstOrCreate($validated);

        return redirect()->back()->with('success', 'Plat programmé.');
    }

    /**
     * Retirer du menu du jour.
     */
    public function destroyDailyMenu(DailyMenu $dailyMenu)
    {
        $dailyMenu->delete();
        return redirect()->back()->with('success', 'Plat retiré du menu.');
    }

    /**
     * Mettre à jour le statut d'une livraison/commande.
     */
    public function updateOrderStatus(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status' => 'required|in:en_attente,en_preparation,en_livraison,livre,annule',
        ]);

        $order->update(['status' => $validated['status']]);

        return redirect()->back()->with('success', 'Statut de la commande mis à jour.');
    }

    /**
     * Enregistrer une vente sur place (Caisse rapide).
     */
    public function storeInRestaurantOrder(Request $request)
    {
        $validated = $request->validate([
            'table_number' => 'required|string|max:50',
            'items' => 'required|array|min:1',
            'items.*.dish_id' => 'required|exists:dishes,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($validated) {
            $totalAmount = 0;
            foreach ($validated['items'] as $item) {
                $totalAmount += $item['price'] * $item['quantity'];
            }

            $order = Order::create([
                'customer_name' => 'Client sur place',
                'customer_phone' => 'N/A',
                'delivery_address' => 'Sur place - ' . $validated['table_number'],
                'total_amount' => $totalAmount,
                'status' => 'livre',
            ]);

            foreach ($validated['items'] as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'dish_id' => $item['dish_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                ]);
            }
        });

        return redirect()->back()->with('success', 'Vente sur place enregistrée !');
    }

    /**
     * Enregistrer une nouvelle catégorie.
     */
    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:categories,name|max:255',
        ]);

        Category::create($validated);

        return redirect()->back()->with('success', 'La nouvelle catégorie a été ajoutée !');
    }
}
