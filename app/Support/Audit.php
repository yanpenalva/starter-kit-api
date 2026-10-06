<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;
use Spatie\Activitylog\Models\Activity;

/**
 * Fail-closed audit writer. Records are inserted synchronously inside the
 * caller's PostgreSQL transaction; the envelope is minimal by approval and
 * every record is asserted to exist, so a skipped insert can never let the
 * enclosing mutation commit silently.
 */
final class Audit
{
    public const EVENT_CLI_BOOTSTRAP = 'cli.bootstrap';

    public const EVENT_PRIVILEGED_CHANGE = 'privileged.change';

    private const ALLOWED_EVENTS = [self::EVENT_CLI_BOOTSTRAP, self::EVENT_PRIVILEGED_CHANGE];

    private const ALLOWED_PROPERTY_KEYS = ['correlation_id', 'actor_type', 'actor_id'];

    /**
     * The single-sourced event allowlist, also exposed to the read feed so
     * its filters can never invent an event outside the approved set.
     *
     * @return list<string>
     */
    public static function allowedEvents(): array
    {
        return self::ALLOWED_EVENTS;
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public static function record(
        string $event,
        string $subjectType,
        int $subjectId,
        string $correlationId,
        array $properties = [],
        string $description = '',
    ): Activity {
        if (! in_array($event, self::ALLOWED_EVENTS, true)) {
            throw new RuntimeException(sprintf('Audit event is not approved: %s', $event));
        }

        $properties = self::minimalProperties($properties + ['correlation_id' => $correlationId]);

        $activity = Activity::query()->create([
            'log_name' => 'privilege',
            'description' => $description === '' ? $event : $description,
            'event' => $event,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'causer_type' => null,
            'causer_id' => null,
            'properties' => $properties,
        ]);

        $exists = Activity::query()
            ->whereKey($activity->getKey())
            ->where('event', $event)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->exists();

        if (! $exists) {
            throw new RuntimeException('Audit record was not persisted; the mutation must not stand.');
        }

        return $activity;
    }

    /**
     * Deny-by-default envelope: only the approved minimal keys survive.
     *
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    private static function minimalProperties(array $properties): array
    {
        $minimal = [];

        foreach ($properties as $key => $value) {
            if (in_array($key, self::ALLOWED_PROPERTY_KEYS, true)) {
                $minimal[$key] = $value;
            }
        }

        return $minimal;
    }
}
