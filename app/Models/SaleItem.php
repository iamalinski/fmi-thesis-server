<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    protected $fillable = [
        'sale_id',
        'article_id',
        'quantity',
        'price',
        'total',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function article()
    {
        return $this->belongsTo(Article::class);
    }
}
