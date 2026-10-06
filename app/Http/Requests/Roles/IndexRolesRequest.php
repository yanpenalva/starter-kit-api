<?php

declare(strict_types=1);

namespace App\Http\Requests\Roles;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Wave B contract: list the catalogue roles with allowlisted filters and
 * bounded pagination. No route binds this request yet.
 */
final class IndexRolesRequest extends FormRequest
{
    public const DEFAULT_PER_PAGE = 15;

    public const MAX_PER_PAGE = 100;

    public function authorize(): bool
    {
        $actor = $this->user();

        return $actor instanceof User && $actor->can(Permission::RolesView->value);
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
        ];
    }

    /**
     * @return array<string, string>
     */
    public function filters(): array
    {
        $search = $this->validated('search', null);

        if (! is_string($search) || $search === '') {
            return [];
        }

        return ['search' => $search];
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
