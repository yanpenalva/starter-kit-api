<?php

declare(strict_types=1);

namespace App\Http\Controllers\Audit;

use App\Http\Requests\Audit\IndexActivitiesRequest;
use App\Http\Resources\Audit\ActivityResource;
use App\Support\Filters\ActivityFilters;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\Activitylog\Models\Activity;

/**
 * Wave B: read-only audit feed. The query is bounded to the privilege
 * trail, filters are allowlisted and validated, and every exposed field
 * comes from the approved minimal envelope.
 */
final class ActivityLogController
{
    /**
     * @return AnonymousResourceCollection<int, ActivityResource>
     */
    public function index(IndexActivitiesRequest $request): AnonymousResourceCollection
    {
        $activities = ActivityFilters::apply(Activity::query(), $request->filters())
            ->where('log_name', 'privilege')
            ->orderByDesc('id')
            ->paginate($request->perPage(), ['*'], 'page', $request->page());

        return ActivityResource::collection($activities);
    }
}
