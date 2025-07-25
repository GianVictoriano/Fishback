<?php

namespace App\Http\Controllers;

use App\Models\GroupChat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GroupChatController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $chats = $user->groupChats()->with('scrumBoard')->get();

        return response()->json($chats);
    }
    //
}
