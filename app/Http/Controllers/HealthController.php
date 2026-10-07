<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\ReadinessChecks;
use Illuminate\Http\JsonResponse;

final class HealthController extends Controller
{
    public function __invoke(ReadinessChecks $readinessChecks): JsonResponse
    {
        $checks = $readinessChecks->run();
        $healthy = ! in_array(false, $checks, true);

        return response()->json([
            'status' => $healthy ? 'ok' : 'error',
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }
}
