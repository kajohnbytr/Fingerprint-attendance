<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reading extends Model
{
    $table->id();
    $table->string('device_id');
    $table->decimal('temperature', 5, 2);
    $table->decimal('humidity', 5, 2);
    $table->timestamps();
}
