<?php

declare(strict_types=1);

namespace App\Support\Filters;

use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

/**
 * Wave B contract: allowlisted query filters for the audit feed. Unknown
 * keys are ignored; the owning request validates the accepted ones.
 */
final class ActivityFilters
{
    /**
     * @param  Builder<Activity>  $query
     * @param  array<string, string>  $filters
     * @return Builder<Activity>
     */
    public static function apply(Builder $query, array $filters): Builder
    {
        $event = $filters['event'] ?? null;

        if (is_string($event) && $event !== '') {
            $query->where('event', $event);
        }

        $subjectType = $filters['subject_type'] ?? null;

        if (is_string($subjectType) && $subjectType !== '') {
            $query->where('subject_type', $subjectType);
        }

        $from = $filters['from'] ?? null;

        if (is_string($from) && $from !== '') {
            $query->whereDate('created_at', '>=', $from);
        }

        $to = $filters['to'] ?? null;

        if (is_string($to) && $to !== '') {
            $query->whereDate('created_at', '<=', $to);
        }

        $causerId = $filters['causer_id'] ?? null;

        if (is_string($causerId) && $causerId !== '') {
            $query->where('causer_id', $causerId);
        }

        return $query;
    }
}
