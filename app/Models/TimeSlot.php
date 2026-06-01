<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class TimeSlot extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'time_slots';

    protected $fillable = [
        'tanggal',
        'jam_mulai',
        'jam_selesai',
        'kapasitas_maksimal',
        'kapasitas_terpakai',
        'list_jam'
    ];

    // Opsional: Casting agar tipe data tetap konsisten
    protected $casts = [
        'tanggal' => 'datetime',
        'kapasitas_maksimal' => 'integer',
        'kapasitas_terpakai' => 'integer',
        'list_jam' => 'array',
    ];
}
