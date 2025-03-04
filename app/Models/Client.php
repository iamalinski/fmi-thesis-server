<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'number',
        'vat_number',
        'acc_person',
        'address',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
}
