<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CoverageRequest;
use Illuminate\Http\Request;

class CoverageRequestController extends Controller
{
    /**
     * Display a listing of coverage requests.
     */
    public function index()
    {
        $requests = CoverageRequest::orderBy('created_at', 'desc')->get();
        
        return response()->json([
            'status' => 'success',
            'data' => $requests
        ]);
    }

    /**
     * Store a newly created coverage request.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_name' => 'required|string|max:255',
            'event_date' => 'required|date',
            'event_location' => 'required|string|max:255',
            'requester_name' => 'required|string|max:255',
            'requester_email' => 'required|email|max:255',
            'description' => 'nullable|string',
        ]);

        $coverageRequest = CoverageRequest::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Coverage request submitted successfully',
            'data' => $coverageRequest
        ], 201);
    }

    /**
     * Display the specified coverage request.
     */
    public function show($id)
    {
        $request = CoverageRequest::findOrFail($id);
        
        return response()->json([
            'status' => 'success',
            'data' => $request
        ]);
    }

    /**
     * Approve a coverage request.
     */
    public function approve($id)
    {
        $request = CoverageRequest::findOrFail($id);
        
        $request->update(['status' => 'approved']);

        return response()->json([
            'status' => 'success',
            'message' => 'Coverage request approved successfully',
            'data' => $request
        ]);
    }

    /**
     * Reject a coverage request.
     */
    public function reject($id)
    {
        $request = CoverageRequest::findOrFail($id);
        
        $request->update(['status' => 'rejected']);

        return response()->json([
            'status' => 'success',
            'message' => 'Coverage request rejected successfully',
            'data' => $request
        ]);
    }

    /**
     * Remove the specified coverage request.
     */
    public function destroy($id)
    {
        $request = CoverageRequest::findOrFail($id);
        $request->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Coverage request deleted successfully'
        ]);
    }
}
