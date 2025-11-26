<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkingHour extends Model
{
    protected $fillable = [
        'user_id',
        'day_of_week',
        'preferred_start_time',
        'preferred_end_time',
        'possible_start_time',
        'possible_end_time',
    ];

    protected function casts(): array
    {
        return [
            'preferred_start_time' => 'datetime:H:i',
            'preferred_end_time' => 'datetime:H:i',
            'possible_start_time' => 'datetime:H:i',
            'possible_end_time' => 'datetime:H:i',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
