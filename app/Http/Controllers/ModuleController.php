<?php

namespace App\Http\Controllers;

use App\Models\Module;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ModuleController extends Controller
{
    // Fetch all available modules
    public function getModules(Request $request)
    {
        Gate::authorize('view-module-management');
        return Module::all();
    }

    // Fetch collaborators that the current user can manage
    public function getCollaborators(Request $request)
    {
        Gate::authorize('view-module-management');

        $user = $request->user();
        $profile = $user->profile;

        if ($profile->level == 3) {
            // Level 3 can manage Level 1 and 2
            $query = Profile::where('role', 'collaborator')->whereIn('level', [1, 2]);
        } elseif ($profile->level == 2) {
            // Level 2 can only manage Level 1
            $query = Profile::where('role', 'collaborator')->where('level', 1);
        } else {
            // Level 1 cannot manage anyone
            return response()->json(['message' => 'Not authorized to manage users'], 403);
        }

        // Eager load the user and modules relationships
        $collaborators = $query->with(['user', 'modules'])->get();

        return response()->json($collaborators);
    }

    // Update modules for a specific collaborator
    public function updateCollaboratorModules(Request $request, Profile $profile)
    {
        Gate::authorize('assign-modules', $profile);

        $user = $request->user();

        // New Rule: Level 2 can only assign specific modules to Level 1
        if ($user->profile && $user->profile->level == 2 && $profile->level == 1) {
            $allowedModuleNames = ['dashboard', 'collaborate', 'review-content'];
            $allowedModuleIds = \App\Models\Module::whereIn('name', $allowedModuleNames)->pluck('id')->toArray();

            $requestedModuleIds = $request->input('modules', []);

            // Check if all requested modules are allowed
            if (array_diff($requestedModuleIds, $allowedModuleIds)) {
                return response()->json(['message' => 'You are only permitted to assign Dashboard, Collaborate, and Review Content modules.'], 403);
            }
        }

        $request->validate([
            'modules' => 'required|array',
            'modules.*' => 'integer|exists:modules,id',
        ]);

        $newModuleIds = $request->modules;
        $currentModuleIds = $profile->modules()->pluck('modules.id')->toArray();

        // Find modules to assign (new ones)
        $toAssign = array_diff($newModuleIds, $currentModuleIds);

        // Find modules to remove (old ones not in new)
        $toRemove = array_diff($currentModuleIds, $newModuleIds);

        // Log assignments
        foreach ($toAssign as $moduleId) {
            \App\Models\ProfileModuleHistory::create([
                'profile_id' => $profile->id,
                'module_id' => $moduleId,
                'action' => 'assigned',
            ]);
        }

        // Log removals
        foreach ($toRemove as $moduleId) {
            \App\Models\ProfileModuleHistory::create([
                'profile_id' => $profile->id,
                'module_id' => $moduleId,
                'action' => 'removed',
            ]);
        }

        // Update the current modules
        $profile->modules()->sync($newModuleIds);

        return response()->json(['message' => 'Modules updated successfully']);
    }

    // Update the position of a specific collaborator
    public function updatePosition(Request $request, Profile $profile)
    {
        Gate::authorize('assign-modules', $profile); // Re-use the same gate for now

        $validated = $request->validate([
            'position' => 'nullable|string|max:255',
        ]);

        $profile->position = $validated['position'];
        $profile->save();

        return response()->json(['message' => 'Position updated successfully.', 'profile' => $profile]);
    }
}
