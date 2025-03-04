<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $fillable = [
        'user_id',
        'client_id',
        'sale_number',
        'date',
        'subtotal',
        'discount',
        'total',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }
}
