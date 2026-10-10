<?php

declare(strict_types=1);

namespace App\Http\Requests\Audit;

use App\Enums\Permission;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Wave B contract: read the audit feed with allowlisted filters and bounded
 * pagination. Events are restricted to the approved Audit allowlist and
 * subject types to the two privileged domains; unknown query keys never
 * reach the query layer.
 */
final class IndexActivitiesRequest extends FormRequest
{
    public const DEFAULT_PER_PAGE = 15;

    public const MAX_PER_PAGE = 100;

    private const SUBJECT_TYPES = ['user', 'role'];

    public function authorize(): bool
    {
        $actor = $this->user();

        return $actor instanceof User && $actor->can(Permission::LogsView->value);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
            'event' => ['sometimes', 'nullable', 'string', Rule::in(Audit::allowedEvents())],
            'subject_type' => ['sometimes', 'nullable', 'string', Rule::in(self::SUBJECT_TYPES)],
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'causer_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function filters(): array
    {
        $filters = [];

        foreach (['event', 'subject_type', 'from', 'to', 'causer_id'] as $key) {
            $value = $this->validated($key, null);

            if ((is_string($value) && $value !== '') || is_int($value)) {
                $filters[$key] = (string) $value;
            }
        }

        return $filters;
    }

    public function page(): int
    {
        $page = $this->validated('page', 1);

        if (is_int($page)) {
            return $page;
        }

        if (is_string($page) && ctype_digit($page)) {
            return (int) $page;
        }

        return 1;
    }

    public function perPage(): int
    {
        $perPage = $this->validated('per_page', self::DEFAULT_PER_PAGE);

        if (is_int($perPage)) {
            return $perPage;
        }

        if (is_string($perPage) && ctype_digit($perPage)) {
            return (int) $perPage;
        }

        return self::DEFAULT_PER_PAGE;
    }
}
