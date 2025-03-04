<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'price',
        'status',
        'description',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }
}
