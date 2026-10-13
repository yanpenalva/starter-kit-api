<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;
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

    public const ACTION_USER_STORE = 'user.store';

    public const ACTION_USER_UPDATE = 'user.update';

    public const ACTION_USER_DESTROY = 'user.destroy';

    public const ACTION_USER_STATUS = 'user.status';

    public const ACTION_USER_ASSIGN_ROLES = 'user.assign_roles';

    public const ACTION_ROLE_STORE = 'role.store';

    public const ACTION_ROLE_UPDATE_PERMISSIONS = 'role.update_permissions';

    public const ACTION_ROLE_DESTROY = 'role.destroy';

    public const ACTION_ROLE_RENAME = 'role.rename';

    public const ACTION_BOOTSTRAP = 'admin.bootstrap';

    private const ALLOWED_EVENTS = [self::EVENT_CLI_BOOTSTRAP, self::EVENT_PRIVILEGED_CHANGE];

    private const ALLOWED_ACTIONS = [
        self::ACTION_USER_STORE,
        self::ACTION_USER_UPDATE,
        self::ACTION_USER_DESTROY,
        self::ACTION_USER_STATUS,
        self::ACTION_USER_ASSIGN_ROLES,
        self::ACTION_ROLE_STORE,
        self::ACTION_ROLE_UPDATE_PERMISSIONS,
        self::ACTION_ROLE_DESTROY,
        self::ACTION_ROLE_RENAME,
        self::ACTION_BOOTSTRAP,
    ];

    private const ALLOWED_PROPERTY_KEYS = ['correlation_id', 'actor_type', 'actor_id', 'action'];

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
     * @return list<string>
     */
    public static function allowedActions(): array
    {
        return self::ALLOWED_ACTIONS;
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

        if (! isset($properties['action'])) {
            throw new RuntimeException('Audit action is not approved.');
        }

        $activity = Activity::query()->create([
            'log_name' => 'privilege',
            'description' => $description === '' ? $event : $description,
            'event' => $event,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'causer_type' => ($properties['actor_type'] ?? null) === 'user' ? User::class : null,
            'causer_id' => ($properties['actor_type'] ?? null) === 'user'
                && is_int($properties['actor_id'] ?? null)
                ? $properties['actor_id']
                : null,
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
    public static function minimalProperties(array $properties): array
    {
        $minimal = [];

        foreach ($properties as $key => $value) {
            $isAllowedValue = match ($key) {
                'correlation_id' => is_string($value) && Str::isUuid($value),
                'actor_type' => in_array($value, ['user', 'cli'], true),
                'actor_id' => $value === null || (is_int($value) && $value > 0),
                'action' => in_array($value, self::ALLOWED_ACTIONS, true),
                default => false,
            };

            if (in_array($key, self::ALLOWED_PROPERTY_KEYS, true) && $isAllowedValue) {
                $minimal[$key] = $value;
            }
        }

        return $minimal;
    }
}
