<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Events\MessageSent;

class ChatMessageController extends Controller
{
    public function index(Request $request, $groupChatId)
    {
        $query = ChatMessage::where('group_chat_id', $groupChatId)->with('user.profile');

        if ($lastMessageId = $request->query('last_message_id')) {
            $query->where('id', '>', $lastMessageId);
        }

        $messages = $query->latest()->get();

        return response()->json($messages);
    }

    public function store(Request $request, $groupChatId)
    {
        $request->validate([
            'message' => 'required|string',
            'system' => 'sometimes|boolean',
        ]);

        $isSystem = $request->boolean('system', false);
        $userId = $isSystem ? null : Auth::id();

        $message = ChatMessage::create([
            'user_id' => $userId,
            'message' => $request->message,
            'group_chat_id' => $groupChatId,
            'system' => $isSystem,
        ]);

        // Eager load the user relationship for the created message
        $message->load('user.profile');

        broadcast(new MessageSent($message))->toOthers();

        return response()->json($message, 201);
    }
}
