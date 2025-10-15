<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApplicationPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ApplicationPeriodController extends Controller
{
    /**
     * Get the current active application period
     */
    public function index()
    {
        $period = ApplicationPeriod::getActive();
        
        if (!$period) {
            return response()->json([
                'message' => 'No application period set'
            ], 404);
        }

        return response()->json($period);
    }

    /**
     * Store or update the application period
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Deactivate all existing periods
        ApplicationPeriod::query()->update(['is_active' => false]);

        // Create new active period
        $period = ApplicationPeriod::create([
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'is_active' => true,
        ]);

        return response()->json($period, 201);
    }

    /**
     * Check if applications are currently open
     */
    public function checkStatus()
    {
        $period = ApplicationPeriod::getActive();
        
        if (!$period) {
            return response()->json([
                'is_open' => true,
                'message' => 'No application period set. Applications are open.',
            ]);
        }

        $isOpen = $period->isCurrentlyActive();
        $message = $isOpen 
            ? 'Applications are currently open.' 
            : ($period->isBeforeStart() 
                ? 'Application period has not yet started.' 
                : 'Application period has ended.');

        return response()->json([
            'is_open' => $isOpen,
            'message' => $message,
            'period' => $period,
        ]);
    }

    /**
     * Delete the application period
     */
    public function destroy($id)
    {
        $period = ApplicationPeriod::findOrFail($id);
        $period->delete();

        return response()->json([
            'message' => 'Application period deleted successfully'
        ]);
    }
}
