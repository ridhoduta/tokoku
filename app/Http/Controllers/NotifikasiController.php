<?php

namespace App\Http\Controllers;

use Google\Client;
use Illuminate\Http\Request;

class NotifikasiController extends Controller
{
    public function sendFCM(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'title' => 'required',
            'body' => 'required',
        ]);

        // Path ke service account
        $serviceAccountPath = storage_path('app/firebase/serviceAccount.json');

        // Membuat Google Client
        $client = new Client();
        $client->setAuthConfig($serviceAccountPath);
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');

        // Mendapatkan access token
        $accessToken = $client->fetchAccessTokenWithAssertion()['access_token'];

        // Dapatkan project ID dari file JSON
        $projectId = json_decode(file_get_contents($serviceAccountPath), true)['project_id'];

        // Endpoint API FCM HTTP v1
        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        // Data notifikasi
        $data = [
            "message" => [
                "token" => $request->token,
                "notification" => [
                    "title" => $request->title,
                    "body" => $request->body
                ]
            ]
        ];

        // Kirim dengan cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $accessToken",
            "Content-Type: application/json",
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        return response()->json(json_decode($response, true));
    }
}
