<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class Tag extends Authenticatable {

    protected $table = 'tag';
    protected $fillable = [
        'name',
        'media_id',
        'photo_grapher_id'
    ];
    public $timestamps = false;
}
