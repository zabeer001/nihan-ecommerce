<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class TestController extends Controller
{
    public function sendEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'to' => ['required', 'email'],
        ]);

        Mail::raw('This is a test email from TapeDB.', function ($message) use ($validated): void {
            $message
                ->to($validated['to'])
                ->subject('TapeDB email test');
        });

        return response()->json([
            'message' => 'Test email sent successfully.',
            'to' => $validated['to'],
        ]);
    }
}
