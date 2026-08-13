<?php
// api/v1/speech.php
// Endpoint to transcribe audio using Google Cloud Speech V2

use App\Services\GoogleSpeechService;

function handle_speech($method, $id, $subaction)
{
    // Allow only POST
    if ($method !== 'POST') {
        api_response(['success' => false, 'message' => 'Méthode non autorisée'], 405);
    }

    // Read Google Cloud config from env or defaults
    $projectId = getenv('GOOGLE_CLOUD_PROJECT') ?: (defined('CONFIG') && isset(CONFIG['google_project']) ? CONFIG['google_project'] : null);
    $location = getenv('GOOGLE_CLOUD_SPEECH_LOCATION') ?: 'global';
    $recognizerId = getenv('GOOGLE_CLOUD_SPEECH_RECOGNIZER') ?: 'default';

    if (empty($projectId)) {
        api_response(['success' => false, 'message' => 'GOOGLE_CLOUD_PROJECT non configuré'], 500);
    }

    $audioContent = null;

    // Prefer file upload field 'file'
    if (!empty($_FILES['file']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
        $audioContent = file_get_contents($_FILES['file']['tmp_name']);
    } else {
        // Allow JSON body with base64 field { "audio": "...base64..." }
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        if (!empty($json['audio'])) {
            $audioContent = base64_decode($json['audio']);
        }
    }

    if ($audioContent === null || $audioContent === false || strlen($audioContent) === 0) {
        api_response(['success' => false, 'message' => 'Aucun fichier audio fourni'], 400);
    }

    // Call service
    $result = GoogleSpeechService::transcribeContent($projectId, $location, $recognizerId, $audioContent);

    if (!$result['success']) {
        api_response(['success' => false, 'message' => 'Transcription échouée', 'error' => $result['error']], 500);
    }

    api_response(['success' => true, 'results' => $result['results']]);
}
