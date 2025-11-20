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
    private $winstonApiKey;

    public function __construct()
    {
        $this->winstonApiKey = env('WINSTON_API_KEY');
    
        // This setup for Copyleaks API interaction is assumed based on previous context.
        // You might need to adjust it based on your actual Copyleaks SDK or client.
    }

    /**
     * Submits text for a plagiarism scan.
     */
    public function submitScan(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:txt,pdf,doc,docx,rtf,ppt,pptx,xls,xlsx|max:51200',
        ]); // url upload removed, only file allowed

        $user = Auth::user();
        $scanId = Str::uuid();

        PlagiarismResult::create([
            'user_id' => $user->id,
            'scan_id' => $scanId,
            'status' => 'pending',
        ]);

        try {
            $file = $request->file('file');
            $ext = strtolower($file->getClientOriginalExtension());
            if ($ext === 'pdf') {
                // PDF extraction
                try {
                    $parser = new \Smalot\PdfParser\Parser();
                    $pdf = $parser->parseFile($file->getRealPath());
                    $text = $pdf->getText();
                } catch (\Exception $e) {
                    return response()->json(['error' => 'Failed to extract text from PDF: ' . $e->getMessage()], 400);
                }
            } elseif (in_array($ext, ['doc', 'docx'])) {
                // DOC/DOCX extraction
                try {
                    $phpWord = \PhpOffice\PhpWord\IOFactory::load($file->getRealPath());
                    $text = '';
                    foreach ($phpWord->getSections() as $section) {
                        $elements = $section->getElements();
                        foreach ($elements as $element) {
                            if (method_exists($element, 'getText')) {
                                $text .= $element->getText() . "\n";
                            }
                        }
                    }
                } catch (\Exception $e) {
                    return response()->json(['error' => 'Failed to extract text from DOC/DOCX: ' . $e->getMessage()], 400);
                }
            } else {
                // Default: treat as plain text
                $text = file_get_contents($file->getRealPath());
            }
            if (mb_strlen($text) < 100) {
                return response()->json(['error' => 'Text must be at least 100 characters for Winston MCP.'], 400);
            }
            $payload = [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'tools/call',
                'params' => [
                    'name' => 'plagiarism-detection',
                    'arguments' => [
                        'text' => $text,
                        'apiKey' => $this->winstonApiKey,
                    ]
                ]
            ];
            Log::info('Winston MCP API payload', ['payload' => $payload]);
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post('https://api.gowinston.ai/mcp/v1', $payload);
            Log::info('Winston MCP API response', ['response' => $response->json()]);
            if ($response->successful() && isset($response['result']['content'][0]['text'])) {
                $resultText = $response['result']['content'][0]['text'];

                // Find the start of the JSON object, as the API includes a text prefix.
                $jsonStart = strpos($resultText, '{');

                if ($jsonStart !== false) {
                    $jsonString = substr($resultText, $jsonStart);
                    $decodedResult = json_decode($jsonString, true);

                    if (json_last_error() === JSON_ERROR_NONE) {
                        // Save decoded result to DB
                        PlagiarismResult::where('scan_id', $scanId)->update([
                            'status' => 'completed',
                            'result' => $decodedResult,
                        ]);

                        // Return the structured result
                        return response()->json([
                            'scan_id' => $scanId,
                            'result' => $decodedResult,
                        ]);
                    } else {
                        // Handle JSON decoding error
                        PlagiarismResult::where('scan_id', $scanId)->update(['status' => 'failed']);
                        Log::error('Winston MCP JSON decode failed', ['raw_result' => $resultText]);
                        return response()->json(['error' => 'Failed to parse plagiarism result from Winston MCP.'], 500);
                    }
                } else {
                    // Could not find the start of the JSON object in the response string
                    PlagiarismResult::where('scan_id', $scanId)->update(['status' => 'failed']);
                    Log::error('Winston MCP response did not contain a JSON object.', ['raw_result' => $resultText]);
                    return response()->json(['error' => 'Invalid response format from plagiarism service.'], 500);
                }
            } else {
                PlagiarismResult::where('scan_id', $scanId)->update(['status' => 'failed']);
                Log::error('Winston MCP submission failed', ['response' => $response->json()]);
                return response()->json(['error' => 'Failed to get plagiarism result from Winston MCP.'], 500);
            }
        } catch (\Exception $e) {
            PlagiarismResult::where('scan_id', $scanId)->update(['status' => 'failed']);
            Log::error('Winston submission failed: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to submit for plagiarism check.'], 500);
        }
    }



    /**
     * Handles the webhook callback from Winston AI.
     */
    public function webhook(Request $request)
    {
        Log::info('Winston webhook received:', $request->all());

        $scanId = $request->input('scan_id');
        $resultData = $request->json()->all();

        if ($scanId) {
            $plagiarismResult = PlagiarismResult::where('scan_id', $scanId)->first();

            if ($plagiarismResult) {
                $plagiarismResult->update([
                    'status' => 'completed',
                    'result' => $resultData, // Store the full webhook payload
                ]);
                Log::info("Plagiarism result for scan {$scanId} saved successfully.");
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
