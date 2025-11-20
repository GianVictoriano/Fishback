<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\PlagiarismResult;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Events\PlagiarismCheckCompleted;

class PlagController extends Controller
{
    private $copyleaksApi;

    public function __construct()
    {
        // para sa hanep na copyleaks
    }

    public function submitScan(Request $request)
    {
        $request->validate([
            'text' => 'required_without_all:file,url|string',
            'file' => 'required_without_all:text,url|file|mimes:txt,doc,docx,pdf|max:2048',
            'url' => 'required_without_all:text,file|url',
        ]);

        $user = Auth::user();
        $scanId = Str::uuid();

        PlagiarismResult::create([
            'user_id' => $user->id,
            'scan_id' => $scanId,
            'status' => 'pending',
        ]);

        try {
            $apiEmail = env('COPYLEAKS_EMAIL');
            $apiKey = env('COPYLEAKS_API_KEY');
            $webhookUrl = env('NGROK_URL') . '/api/plagiarism-webhook';

            $loginResponse = Http::post('https://id.copyleaks.com/v3/account/login/api', [
                'email' => $apiEmail,
                'key' => $apiKey,
            ]);
            $token = $loginResponse->json()['access_token'];

            $properties = [
                'sandbox' => true,
                'webhooks' => ['status' => $webhookUrl . '?status={STATUS}'],
            ];

            if ($request->has('text')) {
            Log::info('Copyleaks submission: submitting TEXT', ['text' => $request->input('text')]);
                Http::withToken($token)->put("https://api.copyleaks.com/v3/scans/submit/file/{$scanId}", [
                    'base64' => base64_encode($request->text),
                    'filename' => 'scan.txt',
                    'properties' => $properties,
                ]);
            } elseif ($request->hasFile('file')) {
                // Log file details before sending
                $file = $request->file('file');
                Log::info('Copyleaks submission: submitting FILE', [
                    'originalName' => $file->getClientOriginalName(),
                    'mimeType' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
                Log::info('Copyleaks submission: submitting FILE details', [
                    'base64' => base64_encode(file_get_contents($file->getRealPath())),
                    'filename' => $file->getClientOriginalName(),
                    'properties' => $properties,
                ]);
                Http::withToken($token)->put("https://api.copyleaks.com/v3/scans/submit/file/{$scanId}", [
                    'base64' => base64_encode(file_get_contents($file->getRealPath())),
                    'filename' => $file->getClientOriginalName(),
                    'properties' => $properties,
                ]);
            } elseif ($request->has('url')) {
                Http::withToken($token)->put("https://api.copyleaks.com/v3/scans/submit/url/{$scanId}", [
                    'url' => $request->input('url'),
                    'properties' => $properties,
                ]);
            }

            return response()->json(['scanId' => $scanId]);

        } catch (\Exception $e) {
            PlagiarismResult::where('scan_id', $scanId)->update(['status' => 'failed']);
            Log::error('Copyleaks submission failed: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to submit for plagiarism check.'], 500);
        }
    }

    /**
     * Handles the webhook callback from Copyleaks.
     */
    public function webhook(Request $request)
    {
        Log::info('Copyleaks webhook received:', $request->all());

        $scanId = $request->input('scannedDocument.scanId');
        $resultData = $request->json()->all();

        if ($scanId) {
            $plagiarismResult = PlagiarismResult::where('scan_id', $scanId)->first();

            if ($plagiarismResult) {
                // When scan is completed, fetch the full results from Copyleaks API
                if (isset($resultData['status']) && $resultData['status'] === 'completed') {
                    try {
                        // Login to get access token
                        $apiEmail = env('COPYLEAKS_EMAIL');
                        $apiKey = env('COPYLEAKS_API_KEY');
                        $loginResponse = Http::post('https://id.copyleaks.com/v3/account/login/api', [
                            'email' => $apiEmail,
                            'key' => $apiKey,
                        ]);
                        $token = $loginResponse->json()['access_token'];

                        // Fetch the complete scan results
                        $resultsResponse = Http::withToken($token)
                            ->get("https://api.copyleaks.com/v3/scans/{$scanId}/result");
                        
                        if ($resultsResponse->successful()) {
                            $completeResults = $resultsResponse->json();
                            Log::info("Complete Copyleaks results for {$scanId}:", $completeResults);
                            
                            // Extract the plagiarism score from the actual structure
                            $score = null;
                            if (isset($completeResults['result']['score'])) {
                                $score = $completeResults['result']['score'];
                            } elseif (isset($completeResults['results']['score']['aggregatedScore'])) {
                                $score = $completeResults['results']['score']['aggregatedScore'];
                            } elseif (isset($completeResults['score']['aggregatedScore'])) {
                                $score = $completeResults['score']['aggregatedScore'];
                            } elseif (isset($completeResults['score'])) {
                                $score = $completeResults['score'];
                            }
                            
                            // Extract AI detection score if available
                            $aiScore = null;
                            if (isset($completeResults['result']['ai'])) {
                                $aiScore = $completeResults['result']['ai'];
                            } elseif (isset($completeResults['ai']['score'])) {
                                $aiScore = $completeResults['ai']['score'];
                            }
                            
                            // Extract structure analysis
                            $analysis = [
                                'identical_words' => $completeResults['result']['identicalWordCounts'] ?? null,
                                'similar_words' => $completeResults['result']['similarWordCounts'] ?? null,
                                'total_words' => $completeResults['result']['textWordCounts'] ?? null,
                                'plagiarized_words' => $completeResults['result']['totalPlagiarismWords'] ?? null,
                                'source_counts' => $completeResults['result']['sourceCounts'] ?? null,
                            ];
                            
                            $plagiarismResult->update([
                                'status' => 'completed',
                                'result' => $completeResults,
                                'score' => $score,
                                'ai_score' => $aiScore,
                                'analysis' => $analysis,
                            ]);
                            
                            Log::info("Plagiarism result for scan {$scanId} saved with score: {$score}, AI score: {$aiScore}");
                        } else {
                            Log::warning("Failed to fetch complete results for {$scanId}");
                            $plagiarismResult->update([
                                'status' => 'completed',
                                'result' => $resultData,
                            ]);
                        }
                    } catch (\Exception $e) {
                        Log::error("Error fetching complete results for {$scanId}: " . $e->getMessage());
                        $plagiarismResult->update([
                            'status' => 'completed',
                            'result' => $resultData,
                        ]);
                    }
                } else {
                    // For other webhook statuses, just store the data
                    $plagiarismResult->update([
                        'result' => $resultData,
                    ]);
                }
            } else {
                Log::warning("Received webhook for unknown scan ID: {$scanId}");
            }
        }

        return response()->json(['status' => 'success']);
    }

    public function checkStatus($scanId)
    {
        $result = PlagiarismResult::where('scan_id', $scanId)->firstOrFail();

        // Ensure the authenticated user is the one who requested the scan
        if (Auth::id() !== $result->user_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $data = $result->toArray();
        // If result is a string, decode it before sending to the frontend
        if (isset($data['result']) && is_string($data['result'])) {
            $decoded = json_decode($data['result'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $data['result'] = $decoded;
            }
        }

        // If we don't have a score yet but the scan is completed, try to extract it from the result
        if (!isset($data['score']) && isset($data['result']) && $data['status'] === 'completed') {
            if (isset($data['result']['result']['score'])) {
                $data['score'] = $data['result']['result']['score'];
            } elseif (isset($data['result']['results']['score']['aggregatedScore'])) {
                $data['score'] = $data['result']['results']['score']['aggregatedScore'];
            } elseif (isset($data['result']['score']['aggregatedScore'])) {
                $data['score'] = $data['result']['score']['aggregatedScore'];
            } elseif (isset($data['result']['score'])) {
                $data['score'] = $data['result']['score'];
            }
        }

        // Extract AI score if not already present
        if (!isset($data['ai_score']) && isset($data['result']) && $data['status'] === 'completed') {
            if (isset($data['result']['result']['ai'])) {
                $data['ai_score'] = $data['result']['result']['ai'];
            } elseif (isset($data['result']['ai']['score'])) {
                $data['ai_score'] = $data['result']['ai']['score'];
            }
        }

        // Extract analysis if not already present
        if (!isset($data['analysis']) && isset($data['result']['result']) && $data['status'] === 'completed') {
            $data['analysis'] = [
                'identical_words' => $data['result']['result']['identicalWordCounts'] ?? null,
                'similar_words' => $data['result']['result']['similarWordCounts'] ?? null,
                'total_words' => $data['result']['result']['textWordCounts'] ?? null,
                'plagiarized_words' => $data['result']['result']['totalPlagiarismWords'] ?? null,
                'source_counts' => $data['result']['result']['sourceCounts'] ?? null,
            ];
        }

        return response()->json($data);
    }
}
