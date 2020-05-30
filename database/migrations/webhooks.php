<?php
use Illuminate\Database\Capsule\Manager as Capsule;

Capsule::schema()->create('webhooks', function($table)
{
    $table->increments('id');
    $table->long('dnid');
    $table->string('url');
});