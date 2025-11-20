<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CoverageRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_name',
        'event_date',
        'event_location',
        'requester_name',
        'requester_email',
        'description',
        'status',
    ];

    protected $casts = [
        'event_date' => 'datetime',
    ];
}
