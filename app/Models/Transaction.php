<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Transaction extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'transactions';
       
    protected $fillable = [
        'user_id',
        'slot_id',
        'status_pembayaran',
        'metode_pembayaran',
        'total_harga',
        'details',
        'ticket',
        'payment',
        'created_at'
    ];

    protected $casts = [
        'details' => 'array',
        'ticket' => 'array',
        'payment' => 'array',
        'created_at' => 'datetime',
    ];

    public function timeSlot()
    {
        return $this->belongsTo(TimeSlot::class, 'slot_id', '_id');
    }
}