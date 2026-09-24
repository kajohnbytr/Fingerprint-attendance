<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reading extends Model
{
    protected $fillable = ['device_id', 'temperature', 'humidity'];

    protected function casts(): array
    {
        return ['temperature' => 'decimal:2', 'humidity' => 'decimal:2'];
    }
}
