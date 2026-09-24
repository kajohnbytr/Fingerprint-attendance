<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'student_number',
        'first_name',
        'last_name',
        'email',
        'subject',
        'year_level',
        'block_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'year_level' => 'integer',
        ];
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(
            Subject::class,
            'enrollments'
        )
            ->withPivot([
                'block_id',
                'enrolled_by',
            ])
            ->withTimestamps();
    }

    public function getFullNameAttribute(): string
    {
        return trim(
            "{$this->first_name} {$this->last_name}"
        );
    }
}
