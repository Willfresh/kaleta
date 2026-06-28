<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyMenu extends Model
{
    protected $fillable = ['day_of_week', 'dish_id'];

    // Une entrée du menu du jour correspond à un plat spécifique
    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class);
    }
}
