<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class PageController extends Controller
{

   public function storeJournalist(Request $request)
    {
    $request->validate([
        'fname' => 'required|string|max:255',
        'lname' => 'required|string|max:255',
        'email' => [
            'required',
            'email',
            'regex:/^[0-9]{2}-[0-9]{5,}@g\.batstate-u\.edu\.ph$/',
            'unique:users,email',
        ],
        'password' => 'required|string|min:8',
    ], [
        'email.regex' => 'Only Batangas State University emails are allowed.',
    ]);

    User::create([
        'fname'    => $request->fname,
        'lname'    => $request->lname,
        'email'    => $request->email,
        'password' => Hash::make($request->password),
        'role'     => 'journalist',
        'position' => $request->position ?? '',
    ]);

    return redirect()->route('admin.journalist')->with('success', 'Journalist account created successfully.');
    }

    public function home() {
        return view('pages.home');
    }

    public function about() {
        return view('pages.about');
 
   }

   
    public function user()
    {
        return view('pages.user');
    }

   
    public function journalist()
    {
        $journalists = User::where('role', 'journalist')->get();
        return view('pages.admin-journalist', compact('journalists'));
    }

    

}
