<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Module;
use Illuminate\Http\Request;

class UserModuleController extends Controller
{
    /**
     * Fetch all available modules.
     */
    public function getModules()
    {
        $modules = Module::all();
        return response()->json($modules);
    }

    /**
     * Fetch all users who can be collaborators, with their assigned modules.
     */
    public function getCollaborators()
    {
        $collaborators = User::with('modules')->get();
        return response()->json($collaborators);
    }

    /**
     * Assign a module to a user.
     */
    public function assignModule(Request $request, User $user)
    {
        $request->validate([
            'module_id' => 'required|exists:modules,id',
        ]);

        $moduleId = $request->input('module_id');

        if ($user->modules()->where('module_id', $moduleId)->exists()) {
            return response()->json(['message' => 'Module already assigned to this user.'], 409);
        }

        $user->modules()->attach($moduleId);

        return response()->json(['message' => 'Module assigned successfully.']);
    }

    /**
     * Revoke a module from a user.
     */
    public function revokeModule(User $user, Module $module)
    {
        if (!$user->modules()->where('module_id', $module->id)->exists()) {
            return response()->json(['message' => 'Module not assigned to this user.'], 404);
        }

        $user->modules()->detach($module->id);

        return response()->json(['message' => 'Module revoked successfully.']);
    }
}
