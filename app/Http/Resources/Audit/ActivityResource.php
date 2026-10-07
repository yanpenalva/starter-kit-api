<?php

declare(strict_types=1);

namespace App\Http\Resources\Audit;

use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Activitylog\Models\Activity;

/**
 * Wave B contract: minimal, allowlisted audit feed entry. The envelope
 * exposes only the approved minimal properties (correlation id and actor
 * identifiers); causer, raw payloads and any unapproved key never leave the
 * API.
 *
 * @property-read Activity $resource
 */
final class ActivityResource extends JsonResource
{
    /**
     * @return array{
     *     id: int,
     *     event: string,
     *     subject_type: string,
     *     subject_id: int,
     *     description: string,
     *     properties: array<string, mixed>,
     *     created_at: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        $activity = $this->resource;

        /** @var array<string, mixed> $storedProperties */
        $storedProperties = $activity->properties?->all() ?? [];
        $properties = Audit::minimalProperties($storedProperties);

        $createdAt = $activity->created_at !== null
            ? $activity->created_at->clone()->utc()->format('Y-m-d\TH:i:s\Z')
            : null;

        return [
            'id' => (int) $activity->id,
            'event' => (string) $activity->event,
            'subject_type' => (string) $activity->subject_type,
            'subject_id' => (int) $activity->subject_id,
            'description' => (string) $activity->event,
            'properties' => $properties,
            'created_at' => $createdAt,
        ];
    }
}
