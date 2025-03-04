<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'eik',
        'vat_number',
        'address',
        'phone',
        'email',
        'bank_name',
        'bank_account',
        'mol',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
