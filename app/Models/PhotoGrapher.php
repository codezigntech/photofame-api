<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class PhotoGrapher extends Authenticatable {

    protected $table = 'photo_grapher';
    protected $fillable = [
        'phonameto_grapher_id'
    ];
    public $timestamps = false;
}
