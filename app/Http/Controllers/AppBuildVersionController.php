<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AppBuildVersionService;
use Illuminate\Http\JsonResponse;

final class AppBuildVersionController extends Controller
{
    public function __invoke(AppBuildVersionService $buildVersionService): JsonResponse
    {
        $payload = $buildVersionService->buildPayload();
        $response = response()->json($payload, 200, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);

        if (is_string($payload['buildId']) && $payload['buildId'] !== '') {
            $response->headers->set('X-App-Build-Id', $payload['buildId']);
        }

        return $response;
    }
}
