<?php

namespace App\Providers;
use Google\Cloud\Firestore\FirestoreClient;

use Illuminate\Support\ServiceProvider;

class FirestoreServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(FirestoreClient::class, function ($app) {
            return new FirestoreClient([
                'projectId' => env('FIREBASE_PROJECT_ID'),
                'keyFilePath' => env('FIREBASE_CREDENTIALS'),
            ]);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
