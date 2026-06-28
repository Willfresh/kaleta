<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = ['order_id', 'dish_id', 'quantity', 'price'];

    // Un article de commande est lié à un plat
    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class);
    }

    // Un article est lié à une commande parent
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
