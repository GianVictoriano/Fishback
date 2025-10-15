<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\User;
use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ApplicantController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $applicants = Applicant::latest()->paginate(10);
        return response()->json($applicants);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:255',
            'sr_code' => 'required|string|max:20|unique:applicants,sr_code',
            'email' => 'required|email|max:255|unique:applicants,email',
            'enrollment_year' => 'required|string|max:20',
            'department' => 'required|string|max:255',
            'desired_role' => 'required|string|max:255',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $applicant = Applicant::create([
                'full_name' => $request->full_name,
                'sr_code' => $request->sr_code,
                'email' => $request->email,
                'enrollment_year' => $request->enrollment_year,
                'department' => $request->department,
                'desired_role' => $request->desired_role,
                'notes' => $request->notes,
                'status' => 'pending',
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Application submitted successfully',
                'data' => $applicant
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to submit application',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $applicant = Applicant::findOrFail($id);
        return response()->json($applicant);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $applicant = Applicant::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'status' => ['required', 'string', Rule::in(['pending', 'approved', 'rejected'])],
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $applicant->update([
                'status' => $request->status,
                'notes' => $request->notes ?? $applicant->notes,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Application updated successfully',
                'data' => $applicant
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update application',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $applicant = Applicant::findOrFail($id);
            $applicant->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Application deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete application',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Accept an applicant and update their profile to collaborator level 1
     */
    public function accept(string $id)
    {
        try {
            $applicant = Applicant::findOrFail($id);

            // Check if applicant is already approved
            if ($applicant->status === 'approved') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Applicant has already been accepted'
                ], 400);
            }

            // Find user by email
            $user = User::where('email', $applicant->email)->first();
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User with this email does not exist'
                ], 400);
            }

            // Update the user's profile
            $profile = Profile::where('user_id', $user->id)->first();
            if ($profile) {
                $profile->update([
                    'role' => 'collaborator',
                    'level' => 1,
                    'position' => $applicant->desired_role,
                ]);
            } else {
                // Create profile if it doesn't exist
                Profile::create([
                    'user_id' => $user->id,
                    'role' => 'collaborator',
                    'level' => 1,
                    'position' => $applicant->desired_role,
                ]);
            }

            // Update applicant status to approved
            $applicant->update([
                'status' => 'approved'
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Applicant accepted successfully',
                'data' => [
                    'profile' => $profile,
                    'applicant' => $applicant
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to accept applicant',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
