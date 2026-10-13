<?php

declare(strict_types=1);

namespace App\Support\Filters;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
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
        $search = self::present($filters['search'] ?? null);
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
            ->when($search !== null, static function (Builder $filtered) use ($search): void {
                assert(is_string($search));
                self::applySearch($filtered, $search);
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

    /**
     * @param  Builder<Activity>  $query
     */
    private static function applySearch(Builder $query, string $search): void
    {
        $pattern = '%'.self::escapeLike($search).'%';

        if (DB::connection()->getDriverName() === 'pgsql') {
            self::applyPostgresSearch($query, $pattern);

            return;
        }

        self::applySqliteSearch($query, $pattern);
    }

    /**
     * @param  Builder<Activity>  $query
     */
    private static function applyPostgresSearch(Builder $query, string $pattern): void
    {
        $query->where(static function (Builder $filtered) use ($pattern): void {
            $filtered
                ->whereRaw("event ilike ? ESCAPE '\\'", [$pattern])
                ->orWhereRaw("properties->>'action' ilike ? ESCAPE '\\'", [$pattern])
                ->orWhereHasMorph('causer', [User::class], static function (Builder $causer) use ($pattern): void {
                    $causer->whereRaw("name ilike ? ESCAPE '\\'", [$pattern]);
                });
        });
    }

    /**
     * @param  Builder<Activity>  $query
     */
    private static function applySqliteSearch(Builder $query, string $pattern): void
    {
        $query->where(static function (Builder $filtered) use ($pattern): void {
            $filtered
                ->whereRaw("event like ? ESCAPE '\\'", [$pattern])
                ->orWhereRaw("json_extract(properties, '$.action') like ? ESCAPE '\\'", [$pattern])
                ->orWhereHasMorph('causer', [User::class], static function (Builder $causer) use ($pattern): void {
                    $causer->whereRaw("name like ? ESCAPE '\\'", [$pattern]);
                });
        });
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    private static function present(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
