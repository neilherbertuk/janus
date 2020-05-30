<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model as Eloquent;

class Webhook extends Eloquent {

    protected $fillable = ['dnid', 'url'];

    public $timestamps = false;

}