<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dish extends Model
{
    protected $fillable = ['name', 'description', 'price', 'image_path', 'category', 'is_available'];

    // Un plat peut être programmé plusieurs fois dans la semaine (ex: Riz blanc le lundi et le jeudi)
    public function dailyMenus(): HasMany
    {
        return $this->hasMany(DailyMenu::class);
    }
}
