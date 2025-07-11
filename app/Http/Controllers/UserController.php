<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
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
}
