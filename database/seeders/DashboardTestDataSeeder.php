<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\GroupChat;
use Illuminate\Support\Facades\DB;

class DashboardTestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all users and group chats
        $users = User::all();
        $groupChats = GroupChat::all();

        if ($users->isEmpty() || $groupChats->isEmpty()) {
            echo "No users or group chats found. Run migrations first.\n";
            return;
        }

        echo "Assigning users to group chats...\n";

        // Assign each user to some group chats
        foreach ($users as $user) {
            // Assign user to 1-3 random group chats
            $numChats = rand(1, min(3, $groupChats->count()));
            $assignedChats = $groupChats->random($numChats);

            foreach ($assignedChats as $chat) {
                // Check if already assigned
                $exists = DB::table('group_chat_members')
                    ->where('user_id', $user->id)
                    ->where('group_chat_id', $chat->id)
                    ->exists();

                if (!$exists) {
                    DB::table('group_chat_members')->insert([
                        'user_id' => $user->id,
                        'group_chat_id' => $chat->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    echo "Assigned user {$user->name} to group chat {$chat->name}\n";
                }
            }
        }

        // Set track status for group chats
        echo "Setting track statuses for group chats...\n";
        foreach ($groupChats as $chat) {
            $track = collect(['pending', 'review', 'approved'])->random();
            $chat->update(['track' => $track]);
            echo "Set group chat {$chat->name} track to {$track}\n";
        }

        // Create some articles for users
        echo "Creating sample articles...\n";
        foreach ($users->take(3) as $user) {
            for ($i = 0; $i < rand(2, 5); $i++) {
                DB::table('articles')->insert([
                    'user_id' => $user->id,
                    'title' => "Sample Article {$i} by {$user->name}",
                    'content' => "This is a sample article content for testing the dashboard.",
                    'status' => collect(['draft', 'published'])->random(),
                    'genre' => collect(['articles', 'opinions', 'sports', 'editorial'])->random(),
                    'published_at' => collect(['draft', 'published'])->random() === 'published' ? now()->subDays(rand(0, 30)) : null,
                    'created_at' => now()->subDays(rand(0, 30)),
                    'updated_at' => now(),
                ]);
            }
            echo "Created articles for user {$user->name}\n";
        }

        echo "Dashboard test data seeding completed!\n";
    }
}
