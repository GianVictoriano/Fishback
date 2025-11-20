<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Check all group chats and their track values
$groupChats = DB::table('group_chats')->select('id', 'name', 'track')->get();

echo "Total group chats: " . $groupChats->count() . "\n\n";

foreach ($groupChats as $chat) {
    echo "ID: {$chat->id} | Name: {$chat->name} | Track: " . ($chat->track ?? 'NULL') . "\n";
}

echo "\n--- Updating NULL tracks to 'pending' ---\n";

$updated = DB::table('group_chats')
    ->whereNull('track')
    ->update(['track' => 'pending']);

echo "Updated {$updated} group chats\n";
