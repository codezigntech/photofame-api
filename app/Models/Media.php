<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class Media extends Authenticatable {

    protected $table = 'media';
    protected $fillable = [
        'photo_grapher_id', 
        'file', 
        'thumb', 
        'type', 
        'width', 
        'height', 
        'colour_string', 
        'primary_colour', 
        'background_colour', 
        'location_id', 
        'views', 
        'downloads', 
        'shares', 
        'favorites', 
        'is_favorite',
        'is_obscene'
    ];
    public $timestamps = false;
}
