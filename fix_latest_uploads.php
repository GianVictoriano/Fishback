<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Fixing the latest uploads that got wrong versions...\n";

// Fix the two latest uploads that should have been 1.5 and 1.6
DB::table('review_content')
    ->where('id', 32)
    ->update(['version' => 1.5]);

DB::table('review_content')
    ->where('id', 33)
    ->update(['version' => 1.6]);

echo "Updated record 32 to version 1.5\n";
echo "Updated record 33 to version 1.6\n";

// Verify the fix
echo "\nVerifying the fix:\n";
$records = DB::table('review_content')
    ->select('id', 'group_id', 'version', 'uploaded_at')
    ->where('group_id', 18)
    ->orderBy('uploaded_at', 'desc')
    ->limit(8)
    ->get();

foreach ($records as $record) {
    echo "ID: {$record->id}, Version: {$record->version}, Uploaded: {$record->uploaded_at}\n";
}

echo "\nFix completed!\n";
