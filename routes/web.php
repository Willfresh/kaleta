<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WelcomeController; // Importation bien configurée
use App\Http\Controllers\AdminDashboardController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// 1. Vitrine de KALETA (Route dynamique reliée au contrôleur)
Route::get('/', [WelcomeController::class, 'index'])->name('home');

// 2. Enregistrement des réservations de table
Route::post('/reservations', [WelcomeController::class, 'storeReservation'])->name('reservations.store');
// En dessous de votre route de réservations :
Route::post('/orders', [WelcomeController::class, 'storeOrder'])->name('orders.store');
// 3. Espace d'administration (Dashboard Breeze)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/categories', [AdminDashboardController::class, 'storeCategory']);
    // Page principale du Dashboard
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    // En dessous de la route 'orders.updateStatus' dans votre groupe d'administration 'auth' :
    Route::post('/in-restaurant-orders', [AdminDashboardController::class, 'storeInRestaurantOrder'])->name('orders.storeInRestaurant');
    // Action de changement de statut d'une réservation
    Route::patch('/reservations/{reservation}/status', [AdminDashboardController::class, 'updateReservationStatus'])->name('reservations.updateStatus');
    // En dessous de la route 'reservations.updateStatus' dans le groupe 'auth' :
    Route::patch('/orders/{order}/status', [AdminDashboardController::class, 'updateOrderStatus'])->name('orders.updateStatus');
    // NOUVELLES ROUTES POUR LA CARTE & LE MENU DU JOUR
    Route::patch('/dishes/{dish}/toggle', [AdminDashboardController::class, 'toggleAvailability'])->name('dishes.toggleAvailability');
    Route::post('/dishes', [AdminDashboardController::class, 'storeDish'])->name('dishes.store');
    Route::post('/daily-menus', [AdminDashboardController::class, 'storeDailyMenu'])->name('daily-menus.store');
    Route::delete('/daily-menus/{dailyMenu}', [AdminDashboardController::class, 'destroyDailyMenu'])->name('daily-menus.destroy');
});
// 4. Gestion du profil de l'administrateur
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
