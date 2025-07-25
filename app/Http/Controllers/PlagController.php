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
        // This setup for Copyleaks API interaction is assumed based on previous context.
        // You might need to adjust it based on your actual Copyleaks SDK or client.
    }

    /**
     * Submits text for a plagiarism scan.
     */
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
                Http::withToken($token)->put("https://api.copyleaks.com/v3/scans/submit/file/{$scanId}", [
                    'base64' => base64_encode($request->text),
                    'filename' => 'scan.txt',
                    'properties' => $properties,
                ]);
            } elseif ($request->hasFile('file')) {
                Http::withToken($token)->put("https://api.copyleaks.com/v3/scans/submit/file/{$scanId}", [
                    'base64' => base64_encode(file_get_contents($request->file('file')->getRealPath())),
                    'filename' => $request->file('file')->getClientOriginalName(),
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
                $plagiarismResult->update([
                    'status' => 'completed',
                    'result' => $resultData, // Store the full webhook payload
                ]);
                Log::info("Plagiarism result for scan {$scanId} saved successfully.");

                // Broadcasting is disabled in favor of polling.

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

        return response()->json($data);
    }
}
