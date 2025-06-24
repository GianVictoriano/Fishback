<?php
//check api
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PlagController extends Controller
{
    public function check(Request $request)
    {
        $email = env('COPYLEAKS_EMAIL');
        $apiKey = env('COPYLEAKS_API_KEY');

        // authentication (need sa api, di toh sa auth may separate na authen ang copy)
        $authResponse = Http::post('https://id.copyleaks.com/v3/account/login/api', [
            'email' => $email,
            'key' => $apiKey,
        ]);

        Log::info('Payload:', ['email' => $email, 'key' => $apiKey]);
        Log::info('Copyleaks Auth Response: ' . $authResponse->body()); 

        if ($authResponse->failed()) {
            return response()->json([
                'error' => 'Authentication with Copyleaks failed.',
                'copyleaks_response' => $authResponse->json(),
            ], 401);
        }

        $accessToken = $authResponse->json()['access_token'];
        Log::info("Access Token: $accessToken");
        // submit na sa api
        $text = $request->input('text', 'Hello world!');
        $scanId = uniqid('scan_');

        $submissionUrl = "https://api.copyleaks.com/v3/scans/submit/file/$scanId";

        $submitResponse = Http::withToken($accessToken)->put($submissionUrl, [
            'base64' => base64_encode($text),
            'filename' => 'file.txt',
            'properties' => [
                'sandbox' => true,
                'webhooks' => [
                    'status' => 'https://9758-2001-fd8-cb75-6b00-5599-6390-d2b1-3f1a.ngrok-free.app/api/copyleaks/webhook/' . $scanId
                ]
            ]
        ]);
        //logs toh kasi ayaw makisama
        Log::info('Copyleaks Submission Status: ' . $submitResponse->status());
        Log::info('Copyleaks Submission Body: ' . $submitResponse->body());

        if ($submitResponse->failed()) {
            return response()->json([
                'error' => 'Submission failed',
                'copyleaks_response' => $submitResponse->json(),
            ], 500);
        }

        return response()->json([
            'message' => 'Text submitted for plagiarism check',
            'scan_id' => $scanId,
        ]);
    }
    //pag kuha ng output mula api
        public function webhook(Request $request, $scanId)
        {
            Log::info("✅ Webhook received for scan ID: $scanId", [
                'payload' => $request->all()
            ]);

            return response()->json(['message' => 'Webhook received']);
        }

}
