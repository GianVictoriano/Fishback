<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class ApplicationPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'start_date',
        'end_date',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Get the currently active application period
     */
    public static function getActive()
    {
        return self::where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Check if applications are currently open
     */
    public static function isOpen()
    {
        $period = self::getActive();
        
        if (!$period) {
            return true; // If no period is set, allow applications (backward compatibility)
        }

        $now = Carbon::now()->startOfDay();
        $start = Carbon::parse($period->start_date)->startOfDay();
        $end = Carbon::parse($period->end_date)->endOfDay();

        return $now->between($start, $end);
    }

    /**
     * Check if the current date is before the start date
     */
    public function isBeforeStart()
    {
        $now = Carbon::now()->startOfDay();
        $start = Carbon::parse($this->start_date)->startOfDay();
        
        return $now->lt($start);
    }

    /**
     * Check if the current date is after the end date
     */
    public function isAfterEnd()
    {
        $now = Carbon::now()->endOfDay();
        $end = Carbon::parse($this->end_date)->endOfDay();
        
        return $now->gt($end);
    }

    /**
     * Check if the period is currently active (within date range)
     */
    public function isCurrentlyActive()
    {
        $now = Carbon::now();
        $start = Carbon::parse($this->start_date)->startOfDay();
        $end = Carbon::parse($this->end_date)->endOfDay();

        return $now->between($start, $end);
    }
}
