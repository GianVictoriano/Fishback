<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /**
     * Display the authenticated user's profile.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request)
    {
        // Eager-load the modules relationship along with the profile
        $profile = Profile::with('modules')->where('user_id', $request->user()->id)->firstOrFail();
        return response()->json($profile);
    }

    /**
     * Update the authenticated user's profile.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $validatedData = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'program' => 'nullable|string|max:255',
            'section' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        // Update User model if name is present
        if ($request->has('name')) {
            $user->update(['name' => $validatedData['name']]);
        }

        // Update Profile model, ensuring existing values are not nulled out if not provided
        $user->profile()->update([
            'name' => $validatedData['name'] ?? $user->profile->name,
            'program' => $validatedData['program'] ?? $user->profile->program,
            'section' => $validatedData['section'] ?? $user->profile->section,
            'description' => $validatedData['description'] ?? $user->profile->description,
        ]);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $user->fresh()->load('profile'),
        ]);
    }
}
