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
            \Log::info('Attempting to send email', [
                'to' => $request->to,
                'subject' => $request->subject,
                'applicant_name' => $request->applicant_name,
                'message_length' => strlen($request->message)
            ]);

            Mail::raw($request->message, function ($mail) use ($request) {
                $mail->to($request->to)
                     ->subject($request->subject)
                     ->from(config('mail.from.address'), config('mail.from.name'));
            });

            \Log::info('Email sent successfully');
            return response()->json([
                'message' => 'Email sent successfully'
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Failed to send email: ' . $e->getMessage());
            \Log::error('Email details:', [
                'to' => $request->to,
                'subject' => $request->subject,
                'mail_config' => [
                    'driver' => config('mail.default'),
                    'host' => config('mail.mailers.smtp.host'),
                    'port' => config('mail.mailers.smtp.port'),
                    'encryption' => config('mail.mailers.smtp.encryption'),
                    'username' => config('mail.mailers.smtp.username'),
                    'from' => config('mail.from.address')
                ]
            ]);
            return response()->json([
                'message' => 'Failed to send email',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
