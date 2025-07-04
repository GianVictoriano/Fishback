<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /* ───────────  VIEWS  ─────────── */
    public function showLoginForm()
    {
        return view('pages.login');
    }

    public function showRegisterForm()
    {
        return view('pages.register');
    }

    /* ───────────  ACTIONS  ─────────── */

    /** Handle registration */
    public function register(Request $request)
{
    $request->validate([
        'name'     => 'required|string|max:255',
        'email'    => [
            'required',
            'email',
            'regex:/^[0-9]{2}-[0-9]{5,}@g\.batstate-u\.edu\.ph$/',
            'unique:users,email',
        ],
        'password' => 'required|string|min:8|confirmed',
    ], [
        'email.regex' => 'Only Batangas State University student emails are allowed.',
    ]);

    $user = User::create([
        'name'     => $request->name,
        'email'    => $request->email,
        'password' => bcrypt($request->password),
        'role'     => 'user', // ✅ now 'user'
    ]);

    return redirect()->route('login')
                     ->with('success', 'Account created! Please login.');
}

    /** Handle login */
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        auth()->login($user);

        // Redirect based on role
        return match ($user->role) {
            'admin'      => redirect()->route('admin'),
            'journalist' => redirect()->route('journalist'),
            'user'       => redirect('/user'), // goes to user.blade.php
            default      => redirect('/'),     // fallback
        };
    }

    /** Handle logout */
    public function logout()
    {
        auth()->logout();
        return redirect()->route('login');
    }
}