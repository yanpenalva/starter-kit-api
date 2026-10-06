<?php

declare(strict_types=1);

namespace App\Http\Requests;

/**
 * Contract for requests exposing validation rules. The base FormRequest does
 * not declare rules(), so this interface keeps static analysis honest about
 * the convention every request in this directory follows.
 */
interface HasRules
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array;
}
