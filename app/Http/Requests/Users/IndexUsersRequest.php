<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Wave B contract: list users with allowlisted filters and bounded
 * pagination. No route binds this request yet.
 */
final class IndexUsersRequest extends FormRequest
{
    public const DEFAULT_PER_PAGE = 15;

    public const MAX_PER_PAGE = 100;

    public function authorize(): bool
    {
        $actor = $this->user();

        return $actor instanceof User && $actor->can(Permission::UsersView->value);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
            'search' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'deactivated'])],
            'role' => ['sometimes', 'string', 'max:64'],
        ];
    }

    /**
     * Only the allowlisted filter keys reach the query layer.
     *
     * @return array<string, string>
     */
    public function filters(): array
    {
        $filters = [];

        foreach (['search', 'status', 'role'] as $key) {
            $value = $this->validated($key, null);

            if (is_string($value) && $value !== '') {
                $filters[$key] = $value;
            }
        }

        return $filters;
    }

    public function page(): int
    {
        $page = $this->validated('page', 1);

        return is_int($page) ? $page : 1;
    }

    public function perPage(): int
    {
        $perPage = $this->validated('per_page', self::DEFAULT_PER_PAGE);

        return is_int($perPage) ? $perPage : self::DEFAULT_PER_PAGE;
    }
}
