<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Package extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'packages';

    // Tambahkan ini agar ID otomatis dikonversi ke string saat jadi JSON
    protected $appends = ['id']; 

    protected $fillable = [
        'name',
        'icon',
        'price',
        'duration',
        'description',
        'status',
        'color_class'
    ];
}
