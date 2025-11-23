<?php

namespace App\Http\Controllers;

use App\Models\WorkingHour;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WorkingHourController extends Controller
{
    /**
     * Get all collaborators' working hours.
     */
    public function index()
    {
        $collaborators = User::whereHas('profile', function ($query) {
            $query->where('level', '!=', 'applicant');
        })->with(['profile', 'workingHours'])->get();

        $data = $collaborators->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'working_hours' => $user->workingHours->map(function ($hour) {
                    return [
                        'day_of_week' => $hour->day_of_week,
                        'preferred_start_time' => $hour->preferred_start_time ? $hour->preferred_start_time->format('H:i') : null,
                        'preferred_end_time' => $hour->preferred_end_time ? $hour->preferred_end_time->format('H:i') : null,
                        'possible_start_time' => $hour->possible_start_time ? $hour->possible_start_time->format('H:i') : null,
                        'possible_end_time' => $hour->possible_end_time ? $hour->possible_end_time->format('H:i') : null,
                    ];
                })
            ];
        });

        return response()->json(['collaborators' => $data]);
    }

    /**
     * Get current user's working hours.
     */
    public function show()
    {
        $user = Auth::user();
        $workingHours = $user->workingHours()->get();

        $data = $workingHours->map(function ($hour) {
            return [
                'day_of_week' => $hour->day_of_week,
                'preferred_start_time' => $hour->preferred_start_time ? $hour->preferred_start_time->format('H:i') : null,
                'preferred_end_time' => $hour->preferred_end_time ? $hour->preferred_end_time->format('H:i') : null,
                'possible_start_time' => $hour->possible_start_time ? $hour->possible_start_time->format('H:i') : null,
                'possible_end_time' => $hour->possible_end_time ? $hour->possible_end_time->format('H:i') : null,
            ];
        });

        return response()->json(['working_hours' => $data]);
    }

    /**
     * Store or update working hours for current user.
     */
    public function store(Request $request)
    {
        $request->validate([
            'working_hours' => 'required|array',
            'working_hours.*.day_of_week' => 'required|string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'working_hours.*.preferred_start_time' => 'nullable|date_format:H:i',
            'working_hours.*.preferred_end_time' => 'nullable|date_format:H:i',
            'working_hours.*.possible_start_time' => 'nullable|date_format:H:i',
            'working_hours.*.possible_end_time' => 'nullable|date_format:H:i',
        ]);

        $user = Auth::user();
        $workingHoursData = $request->input('working_hours');

        foreach ($workingHoursData as $data) {
            // Create a new entry for each time slot instead of updating existing ones
            WorkingHour::create([
                'user_id' => $user->id,
                'day_of_week' => $data['day_of_week'],
                'preferred_start_time' => $data['preferred_start_time'],
                'preferred_end_time' => $data['preferred_end_time'],
                'possible_start_time' => $data['possible_start_time'],
                'possible_end_time' => $data['possible_end_time'],
            ]);
        }

        return response()->json(['message' => 'Working hours updated successfully']);
    }
}
