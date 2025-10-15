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
            'is_anonymous' => 'nullable|boolean',
        ]);

        // Update User model if name is present
        if ($request->has('name')) {
            $user->update(['name' => $validatedData['name']]);
        }

        // Update or create Profile model
        $profileData = [];
        
        if (isset($validatedData['name'])) {
            $profileData['name'] = $validatedData['name'];
        }
        if (isset($validatedData['program'])) {
            $profileData['program'] = $validatedData['program'];
        }
        if (isset($validatedData['section'])) {
            $profileData['section'] = $validatedData['section'];
        }
        if (isset($validatedData['description'])) {
            $profileData['description'] = $validatedData['description'];
        }
        if (isset($validatedData['is_anonymous'])) {
            $profileData['is_anonymous'] = $validatedData['is_anonymous'] ? 1 : 0;
            
            // Generate random anonymous name if enabling anonymous mode
            if ($validatedData['is_anonymous']) {
                $profileData['anonymous_name'] = $this->generateAnonymousName();
            }
        }

        // Use updateOrCreate to handle both cases
        $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            $profileData
        );

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $user->fresh()->load('profile'),
        ]);
    }

    /**
     * Generate a random anonymous name (combination of adjective + noun)
     */
    private function generateAnonymousName()
    {
        $adjectives = [
            'Swift', 'Silent', 'Brave', 'Clever', 'Wise', 'Bold', 'Calm', 'Fierce',
            'Gentle', 'Noble', 'Quick', 'Bright', 'Dark', 'Golden', 'Silver', 'Azure',
            'Crimson', 'Emerald', 'Mystic', 'Ancient', 'Wild', 'Free', 'Proud', 'Loyal'
        ];
        
        $nouns = [
            'Eagle', 'Wolf', 'Tiger', 'Lion', 'Bear', 'Fox', 'Hawk', 'Raven',
            'Dragon', 'Phoenix', 'Falcon', 'Panther', 'Lynx', 'Otter', 'Badger',
            'Mountain', 'River', 'Ocean', 'Forest', 'Storm', 'Thunder', 'Lightning',
            'Shadow', 'Flame', 'Wind', 'Star', 'Moon', 'Sun', 'Sky', 'Cloud'
        ];
        
        $adjective = $adjectives[array_rand($adjectives)];
        $noun = $nouns[array_rand($nouns)];
        
        return $adjective . ' ' . $noun;
    }
}
