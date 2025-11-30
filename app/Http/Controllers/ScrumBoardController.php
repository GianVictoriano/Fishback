<?php

namespace App\Http\Controllers;

use App\Models\ScrumBoard;
use App\Models\GroupChat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ScrumBoardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string|max:100',
            'deadline' => 'nullable|date',
            'collaborators' => 'required|array',
            'collaborators.*' => 'exists:users,id', // Ensure collaborators are valid users
            'lead_reviewer_id' => 'required|exists:users,id', // Lead reviewer is required
        ]);

        try {
            $scrumBoard = DB::transaction(function () use ($validated, $request) {
                // 1. Create the Scrum Board
                $board = ScrumBoard::create([
                    'title' => $validated['title'],
                    'category' => $validated['category'],
                    'deadline' => $validated['deadline'],
                    'lead_reviewer_id' => $validated['lead_reviewer_id'],
                    'created_by' => Auth::id(),
                ]);

                // 2. Create the associated Group Chat
                $chat = $board->groupChat()->create([
                    'name' => $board->title,
                    'status' => 'active',
                ]);

                // 3. Attach collaborators and the creator to the chat
                $memberIds = $validated['collaborators'];
                $memberIds[] = Auth::id(); // Add the creator to the chat
                $chat->members()->attach(array_unique($memberIds));

                return $board;
            });

            // Load the members relationship for the response
            $scrumBoard->load('members');

            return response()->json([
                'message' => 'Scrum board and group chat created successfully!',
                'scrum_board' => $scrumBoard
            ], 201);

        } catch (\Exception $e) {
            // Log the error for debugging
            report($e);

            return response()->json(['message' => 'An error occurred while creating the scrum board.'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $scrumBoard = ScrumBoard::findOrFail($id);
            
            $validated = $request->validate([
                'lead_reviewer_id' => 'sometimes|exists:users,id',
                'title' => 'sometimes|string|max:255',
                'category' => 'sometimes|string|max:100',
                'deadline' => 'nullable|date',
            ]);
            
            // Update only the fields that are provided
            if (isset($validated['lead_reviewer_id'])) {
                $scrumBoard->lead_reviewer_id = $validated['lead_reviewer_id'];
            }
            if (isset($validated['title'])) {
                $scrumBoard->title = $validated['title'];
            }
            if (isset($validated['category'])) {
                $scrumBoard->category = $validated['category'];
            }
            if (isset($validated['deadline'])) {
                $scrumBoard->deadline = $validated['deadline'];
            }
            
            $scrumBoard->save();
            
            return response()->json([
                'message' => 'Scrum board updated successfully.',
                'scrum_board' => $scrumBoard
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update scrum board.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
