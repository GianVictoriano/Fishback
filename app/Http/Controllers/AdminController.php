<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Profile;
use App\Models\Module;

class AdminController extends Controller
{
    public function createSecretAdmin(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        try {
            DB::beginTransaction();

            // Check if admin already exists
            $existingUser = User::where('email', 'pubadmin@g.batstate-u.edu.ph')->first();
            if ($existingUser) {
                // Return existing user with profile
                $user = User::with('profile')->find($existingUser->id);
                $token = $user->createToken('admin-token')->plainTextToken;
                
                DB::commit();
                return response()->json([
                    'success' => true,
                    'message' => 'Admin already exists',
                    'user' => $user,
                    'token' => $token
                ]);
            }

            // Create user record
            $user = new User();
            $user->name = 'Publication Admin';
            $user->email = 'pubadmin@g.batstate-u.edu.ph';
            $user->password = Hash::make('fisherman');
            $user->email_verified_at = now();
            $user->google_id = 'secret-admin-' . uniqid();
            $user->save();

            // Create profile record with dummy data
            $profile = new Profile();
            $profile->user_id = $user->id;
            $profile->role = 'collaborator'; // Set as collaborator
            $profile->level = 3; // Set as level 3
            $profile->position = 'Publication Administrator';
            $profile->avatar = null;
            $profile->name = 'Publication Admin';
            $profile->program = 'Computer Science';
            $profile->section = 'A';
            $profile->description = 'System administrator for Fisherman Publication';
            $profile->is_anonymous = 0;
            $profile->anonymous_name = null;
            $profile->save();

            // Create modules for the profile (all modules for admin access)
            $modules = [
                ['name' => 'articles'],
                ['name' => 'sports'],
                ['name' => 'opinion'],
                ['name' => 'editorial'],
                ['name' => 'creative'],
                ['name' => 'literary'],
                ['name' => 'forum'],
                ['name' => 'recruitment'],
                ['name' => 'events'],
                ['name' => 'admin'],
                ['name' => 'manage_users'],
            ];

            $profile->modules()->createMany($modules);

            // Create API token
            $token = $user->createToken('admin-token')->plainTextToken;

            // Load user with profile and modules
            $userWithProfile = User::with(['profile.modules'])->find($user->id);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Admin user and profile created successfully',
                'user' => $userWithProfile,
                'token' => $token
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Secret admin creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create admin: ' . $e->getMessage()
            ], 500);
        }
    }

    public function assignCollaborator(Request $request, $userId)
    {
        $request->validate([
            'level' => 'required|integer|min:1|max:3',
            'role' => 'required|in:user,collaborator,editor,adviser',
        ]);

        try {
            DB::beginTransaction();

            $user = User::with(['profile', 'profile.modules'])->find($userId);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            $profile = $user->profile;
            if (!$profile) {
                // Create profile if it doesn't exist
                $profile = new Profile();
                $profile->user_id = $user->id;
                $profile->name = $user->name;
                $profile->role = $request->role;
                $profile->level = $request->level;
                $profile->save();
            } else {
                // Update existing profile
                $profile->level = $request->level;
                $profile->role = $request->role;
                $profile->save();
            }

            // If assigning as collaborator, give them all modules
            if ($request->role === 'collaborator' && $request->level === 3) {
                $modules = [
                    ['name' => 'articles'],
                    ['name' => 'sports'],
                    ['name' => 'opinion'],
                    ['name' => 'editorial'],
                    ['name' => 'creative'],
                    ['name' => 'literary'],
                    ['name' => 'forum'],
                    ['name' => 'recruitment'],
                    ['name' => 'events'],
                    ['name' => 'admin'],
                    ['name' => 'manage_users'],
                ];

                // Delete existing modules first
                $profile->modules()->delete();
                
                // Add modules one by one to avoid mass assignment issues
                foreach ($modules as $moduleData) {
                    try {
                        $module = new Module();
                        $module->name = $moduleData['name'];
                        $module->profile_id = $profile->id;
                        $module->save();
                    } catch (\Exception $moduleError) {
                        Log::error('Failed to create module', [
                            'module' => $moduleData['name'],
                            'error' => $moduleError->getMessage()
                        ]);
                    }
                }
            }

            DB::commit();

            // Reload user with updated profile and modules
            $updatedUser = User::with(['profile.modules'])->find($userId);

            return response()->json([
                'success' => true,
                'message' => 'User assigned as collaborator successfully',
                'user' => $updatedUser
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Assign collaborator failed', [
                'user_id' => $userId,
                'request_data' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to assign collaborator: ' . $e->getMessage()
            ], 500);
        }
    }
}
