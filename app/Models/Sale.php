<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Sale extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'sales';
    protected $primaryKey = '_id';

    protected $fillable = [
        'user_id',
        'products',
        'total',
        'payment_method',
        'status',
        'customer_info'
    ];

    protected $casts = [
        '_id' => 'string',
        'products' => 'array',
        'total' => 'decimal:2',
        'created_at' => 'datetime'
    ];

    // Relación con el usuario (vendedor)
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}