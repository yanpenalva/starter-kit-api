<?php

declare(strict_types=1);

namespace App\Support\Filters;

use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

final class ActivityFilters
{
    /**
     * @param  Builder<Activity>  $query
     * @param  array<string, string>  $filters
     * @return Builder<Activity>
     */
    public static function apply(Builder $query, array $filters): Builder
    {
        $event = self::present($filters['event'] ?? null);
        $action = self::present($filters['action'] ?? null);
        $subjectType = self::present($filters['subject_type'] ?? null);
        $from = self::present($filters['from'] ?? null);
        $to = self::present($filters['to'] ?? null);
        $causerId = self::present($filters['causer_id'] ?? null);

        return $query
            ->when($event !== null, static function (Builder $filtered) use ($event): void {
                assert(is_string($event));
                $filtered->where('event', $event);
            })
            ->when($action !== null, static function (Builder $filtered) use ($action): void {
                assert(is_string($action));
                $filtered->where('properties->action', $action);
            })
            ->when($subjectType !== null, static function (Builder $filtered) use ($subjectType): void {
                assert(is_string($subjectType));
                $filtered->where('subject_type', $subjectType);
            })
            ->when($from !== null, static function (Builder $filtered) use ($from): void {
                assert(is_string($from));
                $filtered->whereDate('created_at', '>=', $from);
            })
            ->when($to !== null, static function (Builder $filtered) use ($to): void {
                assert(is_string($to));
                $filtered->whereDate('created_at', '<=', $to);
            })
            ->when($causerId !== null, static function (Builder $filtered) use ($causerId): void {
                assert(is_string($causerId));
                $filtered->where('causer_id', $causerId);
            });
    }

    private static function present(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
