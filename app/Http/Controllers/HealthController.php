<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\ReadinessChecks;
use Dedoc\Scramble\Attributes\Response as OpenApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class HealthController extends Controller
{
    #[OpenApiResponse(status: 200, type: 'array{status: string, checks: array{database: bool, redis: bool, queue: bool}}')]
    #[OpenApiResponse(status: 503, type: 'array{status: string, checks: array{database: bool, redis: bool, queue: bool}}')]
    public function __invoke(ReadinessChecks $readinessChecks): JsonResponse
    {
        $checks = $readinessChecks->run();
        $healthy = ! in_array(false, $checks, true);

        return response()->json([
            'status' => $healthy ? 'ok' : 'error',
            'checks' => $checks,
        ], $healthy ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE);
    }
}
