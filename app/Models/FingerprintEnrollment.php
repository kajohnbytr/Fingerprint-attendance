<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FingerprintEnrollment extends Model
{
    protected $table = 'fingerprint_enrollment';

    protected $fillable = [
        'user_id',
        'device_id',
        'slot',
        'status',
        'error',
    ];
}
