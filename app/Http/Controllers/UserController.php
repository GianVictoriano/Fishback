<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function me(Request $request)
    {
        return $request->user();
    }

    /**
     * Display a listing of all users.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        // We use `with('profile')` to eager-load the profile relationship.
        // This is more efficient than loading it for each user individually.
        $users = User::with('profile')->get();

        return response()->json([
            'users' => $users
        ]);
    }

    /**
     * Display a specific user's profile.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $user = User::with('profile')->findOrFail($id);

        return response()->json($user);
    }
}
