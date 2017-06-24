<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class Location extends Authenticatable {

    protected $table = 'location';
    protected $fillable = [
        'name',
        'latitude',
        'longitude'
    ];
    public $timestamps = false;
}
