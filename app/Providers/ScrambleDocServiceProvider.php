<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use App\OpenApi\ContractDocumentTransformer;
use Dedoc\Scramble\Scramble;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class ScrambleDocServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Gate::define('viewApiDocs', static function (?User $user): bool {
            if ($user === null) {
                return false;
            }
            if ($user->deactivated_at !== null) {
                return false;
            }
            if ($user->email_verified_at === null) {
                return false;
            }

            return $user->hasRole(Role::Admin->value);
        });

        Scramble::afterOpenApiGenerated(new ContractDocumentTransformer);
    }
}
