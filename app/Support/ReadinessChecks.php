<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class ReadinessChecks
{
    /** @return array{database: bool, redis: bool, queue: bool} */
    public function run(): array
    {
        return [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'queue' => $this->checkQueue(),
        ];
    }

    private function checkDatabase(): bool
    {
        try {
            DB::selectOne('select 1');
        } catch (Throwable) {
            return false;
        }

        return true;
    }

    private function checkRedis(): bool
    {
        try {
            $response = Redis::ping();

            return $response === true || $response === 'PONG';
        } catch (Throwable) {
            return false;
        }
    }

    private function checkQueue(): bool
    {
        try {
            $window = config('health.failed_jobs_window_minutes');

            return ! DB::table('failed_jobs')
                ->where('failed_at', '>=', Carbon::now()->subMinutes(is_numeric($window) ? (int) $window : 5))
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }
}
