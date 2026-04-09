<?php

declare(strict_types=1);

use App\Events\TestNotification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Facades\Route;
use Spatie\Browsershot\Browsershot;

/*
|--------------------------------------------------------------------------
| Testing Routes
|--------------------------------------------------------------------------
|
| Routes for testing and debugging purposes including broadcast tests,
| Pusher notification tests, and PDF generation tests.
|
| These routes can be conditionally loaded in non-production environments.
|
*/

// Test broadcast
Route::get('/test-broadcast', function () {
    broadcast(new class implements ShouldBroadcast
    {
        public function broadcastOn()
        {
            return new Channel('test-notifications');
        }

        public function broadcastAs()
        {
            return 'test-event';
        }
    });

    return 'Test event broadcasted!';
});

// Test route for Pusher notifications
Route::get('/test-pusher-notification', function () {
    $message = 'Test Pusher notification at '.now()->toDateTimeString();
    event(new TestNotification($message));

    return response()->json([
        'success' => true,
        'message' => 'Test notification sent: '.$message,
        'pusher_config' => [
            'app_id' => config('broadcasting.connections.pusher.app_id'),
            'key' => config('broadcasting.connections.pusher.key'),
            'cluster' => config('broadcasting.connections.pusher.options.cluster'),
            'host' => config('broadcasting.connections.pusher.options.host'),
            'scheme' => config('broadcasting.connections.pusher.options.scheme'),
        ],
    ]);
})->name('test.pusher.notification');

// Test route for PDF generation (authenticated)
Route::middleware(['auth'])->group(function (): void {
    Route::get('/test-pdf-generation', function () {
        try {
            $html = '<html><body style="padding: 50px; font-family: Arial;">
            <h1>Test PDF Generation</h1>
            <p>This is a test PDF to verify Browsershot is working correctly in production.</p>
            <p>Generated at: '.now().'</p>
            <ul>
                <li>Chrome Path: '.(env('CHROME_PATH') ?: '/usr/bin/chromium').'</li>
                <li>Environment: '.config('app.env').'</li>
            </ul>
        </body></html>';

            $browsershot = Browsershot::html($html)
                ->setChromePath('/usr/bin/google-chrome-stable')
                ->setOption('args', [
                    '--no-sandbox',
                    '--disable-setuid-sandbox',
                    '--disable-dev-shm-usage',
                    '--disable-gpu',
                    '--headless=new',
                    '--disable-crash-reporter',
                    '--crash-dumps-dir=/tmp',
                ])
                ->format('A4')
                ->margins(10, 10, 10, 10)
                ->printBackground();

            $pdf = $browsershot->pdf();

            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="test.pdf"',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'chrome_path' => env('CHROME_PATH') ?: '/usr/bin/google-chrome-stable',
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    })->name('test.pdf');
});
