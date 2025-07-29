<?php

namespace App\Http\Controllers;

use App\Models\GroupChat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GroupChatController extends Controller
{
    public function getGroupChats(Request $request)
    {
        // This log will tell us if authentication was successful
        Log::channel('stderr')->info('Inside getGroupChats. Authenticated User: ', ['user' => $request->user()]);

        if (!$request->user()) {
            Log::channel('stderr')->error('Authentication failed. User is null.');
            // The default 401 response will be sent automatically by the middleware
            return;
        }

        // ... your existing logic to fetch group chats
        $user = $request->user();
        $groupChats = $user->groupChats()->with('latestMessage.user')->get();
        return response()->json($groupChats);
    }
    public function index()
    {
        $user = Auth::user();
        $chats = $user->groupChats()->with('scrumBoard')->get();

        return response()->json($chats);
    }
    //
}
