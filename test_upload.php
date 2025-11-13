<?php

require __DIR__.'/vendor/autoload.php';

use Illuminate\Http\Request;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/routes/web.php',
        commands: __DIR__.'/routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

// Test file upload functionality
try {
    $request = new Request();
    
    // Test validation
    $validator = validator()->make([
        'file' => 'test.txt',
        'group_id' => 1,
        'user_id' => 1,
    ], [
        'file' => 'required|file|mimes:txt,pdf,doc,docx,rtf,ppt,pptx,xls,xlsx|max:51200',
        'group_id' => 'required|integer|exists:group_chats,id',
        'user_id' => 'required|integer|exists:users,id',
    ]);

    if ($validator->fails()) {
        echo 'Validation failed: ' . json_encode($validator->errors());
    } else {
        echo 'Validation passed';
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
