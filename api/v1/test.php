<?php
/**
 * api/v1/test.php
 * 
 * Simple test endpoint to verify API routing is working.
 */

function handle_test(string $method, ?string $id, ?string $subaction): void
{
    $logfile = __DIR__ . '/../../storage/logs/api-voice.log';
    @file_put_contents($logfile, "[test] TEST ENDPOINT CALLED!\\n", FILE_APPEND);
    
    api_response([
        'success' => true,
        'message' => 'Test endpoint works!',
        'method' => $method,
        'id' => $id,
        'subaction' => $subaction,
        'session_user_id' => \App\Core\Auth::check() ? \App\Core\Auth::id() : null,
        'timestamp' => date('Y-m-d H:i:s'),
    ]);
}
