<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Schedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'block_id',
        'subject_code',
        'subject_name',
        'day',
        'start_time',
        'end_time',
        'room',
        'instructor',
    ];

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }
}
