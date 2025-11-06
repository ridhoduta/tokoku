<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WhatsApp extends Controller
{
    public function sendMessage(Request $request)
    {
        $phone = $request->phone; // nomor tujuan, contoh: 6281234567890
        $message = $request->message; // isi pesan

        $response = Http::withHeaders([
            'Authorization' => env('FONNTE_API_KEY')
        ])->post(env('FONNTE_API_URL'), [
            'target' => $phone,
            'message' => $message,
        ]);

        return response()->json($response->json());
    }

    function coba() {
        return "anjai";
        
    }
}
