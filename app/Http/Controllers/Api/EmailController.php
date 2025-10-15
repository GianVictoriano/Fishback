<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class EmailController extends Controller
{
    public function sendEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'to' => 'required|email',
            'subject' => 'required|string',
            'message' => 'required|string',
            'applicant_name' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            Mail::raw($request->message, function ($mail) use ($request) {
                $mail->to($request->to)
                     ->subject($request->subject)
                     ->from(config('mail.from.address'), config('mail.from.name'));
            });

            return response()->json([
                'message' => 'Email sent successfully'
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Failed to send email: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to send email',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
