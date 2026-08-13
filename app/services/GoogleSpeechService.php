<?php
namespace App\Services;

use Google\Cloud\Speech\V2\Client\SpeechClient;
use Google\Cloud\Speech\V2\RecognizeRequest;
use Google\Cloud\Speech\V2\RecognitionConfig;
use Google\Cloud\Speech\V2\AutoDetectDecodingConfig;

class GoogleSpeechService
{
    /**
     * Transcribe audio content using Google Cloud Speech V2 Recognize.
     * @param string $projectId
     * @param string $location
     * @param string $recognizerId
     * @param string $content Binary audio content
     * @return array ['success'=>bool,'results'=>array,'error'=>string|null]
     */
    public static function transcribeContent(string $projectId, string $location, string $recognizerId, string $content): array
    {
        if (!class_exists(SpeechClient::class)) {
            return ['success' => false, 'results' => [], 'error' => 'Google Cloud Speech client not installed. Run: composer require google/cloud-speech'];
        }

        $apiEndpoint = ($location === 'global') ? null : sprintf('%s-speech.googleapis.com', $location);

        try {
            $speech = new SpeechClient(['apiEndpoint' => $apiEndpoint]);

            $recognizerName = SpeechClient::recognizerName($projectId, $location, $recognizerId);

            $config = (new RecognitionConfig())
                ->setModel('default')
                ->setAutoDecodingConfig(new AutoDetectDecodingConfig());

            $request = (new RecognizeRequest())
                ->setRecognizer($recognizerName)
                ->setConfig($config)
                ->setContent($content);

            $response = $speech->recognize($request);
            $results = [];
            foreach ($response->getResults() as $result) {
                $alternatives = $result->getAlternatives();
                if (count($alternatives) === 0) continue;
                $mostLikely = $alternatives[0];
                $results[] = [
                    'transcript' => $mostLikely->getTranscript(),
                    'confidence' => $mostLikely->getConfidence(),
                ];
            }

            $speech->close();

            return ['success' => true, 'results' => $results, 'error' => null];
        } catch (\Throwable $e) {
            return ['success' => false, 'results' => [], 'error' => $e->getMessage()];
        }
    }
}
