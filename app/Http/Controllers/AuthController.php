<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules;

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

    public function showForgotPasswordForm()
    {
        return view('pages.forgot-password'); // Make sure this Blade file exists
    }

    public function showResetPasswordForm($token)
    {
        return view('pages.reset-password', ['token' => $token]); // Make sure this Blade file exists
    }

    /* ───────────  ACTIONS  ─────────── */

    // Registration
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
            'role'     => 'user', // default role
        ]);

        return redirect()->route('login')->with('success', 'Account created! Please login.');
    }

    // Login
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

        return match ($user->role) {
            'admin'      => redirect()->route('admin'),
            'journalist' => redirect()->route('journalist'),
            'user'       => redirect('/user'),
            default      => redirect('/'),
        };
    }

    // Logout
    public function logout()
    {
        auth()->logout();
        return redirect()->route('login');
    }

    // ✅ Forgot Password (send reset link)
    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? back()->with('status', __($status))
            : back()->withErrors(['email' => __($status)]);
    }

    // ✅ Reset Password (set new password)
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', __($status))
            : back()->withErrors(['email' => [__($status)]]);
    }
}