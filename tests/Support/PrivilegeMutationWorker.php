<?php

declare(strict_types=1);

use App\Actions\Roles\UpdateRolePermissions;
use App\Actions\Users\SetUserStatus;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role as PermissionRole;
use Symfony\Component\HttpKernel\Exception\HttpException;

$worktreeRoot = dirname(__DIR__, 2);
require $worktreeRoot.'/vendor/autoload.php';

spl_autoload_register(static function (string $class) use ($worktreeRoot): void {
    $prefixes = [
        'App\\' => $worktreeRoot.'/app/',
        'Database\\Factories\\' => $worktreeRoot.'/database/factories/',
        'Database\\Seeders\\' => $worktreeRoot.'/database/seeders/',
        'Tests\\' => $worktreeRoot.'/tests/',
    ];

    foreach ($prefixes as $prefix => $directory) {
        if (! str_starts_with($class, $prefix)) {
            continue;
        }

        $path = $directory.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';

        if (is_file($path)) {
            require $path;
        }

        return;
    }
}, true, true);

$application = require $worktreeRoot.'/bootstrap/app.php';

if (! $application instanceof Application) {
    throw new RuntimeException('Privilege action worker could not create the Laravel application.');
}

$application->make(Kernel::class)->bootstrap();

if (DB::getDefaultConnection() !== 'pgsql'
    || config('database.connections.pgsql.database') !== 'starter_api_test'
    || config('database.connections.pgsql.url') !== null) {
    throw new RuntimeException('Privilege action worker only runs on the isolated PostgreSQL test database.');
}

$stdin = fgets(STDIN);

if (! is_string($stdin)) {
    throw new RuntimeException('Privilege action worker did not receive its input.');
}

$payload = json_decode($stdin, true, flags: JSON_THROW_ON_ERROR);

if (! is_array($payload)
    || ! is_string($payload['action'] ?? null)
    || ! is_int($payload['actor_id'] ?? null)
    || ! is_int($payload['target_id'] ?? null)) {
    throw new InvalidArgumentException('Privilege action worker received an invalid payload.');
}

$permissionNames = $payload['permission_names'] ?? [];

if (! is_array($permissionNames) || ! array_is_list($permissionNames)) {
    throw new InvalidArgumentException('Privilege action worker received invalid permission names.');
}

foreach ($permissionNames as $permissionName) {
    if (! is_string($permissionName)) {
        throw new InvalidArgumentException('Privilege action worker received a non-string permission name.');
    }
}

$actor = User::query()->findOrFail($payload['actor_id']);
$mutation = match ($payload['action']) {
    'set_user_status' => static function () use ($actor, $payload): void {
        $target = User::query()->findOrFail($payload['target_id']);
        app(SetUserStatus::class)->run($actor, $target, 'deactivated');
    },
    'update_role_permissions' => static function () use ($actor, $payload, $permissionNames): void {
        $target = PermissionRole::query()->findOrFail($payload['target_id']);
        app(UpdateRolePermissions::class)->run($actor, $target, $permissionNames);
    },
    default => throw new InvalidArgumentException('Privilege action worker received an unknown action.'),
};

$backendPid = DB::connection()->scalar('SELECT pg_backend_pid()');

if (! is_int($backendPid)) {
    throw new RuntimeException('Privilege action worker could not identify its PostgreSQL connection.');
}

fwrite(STDOUT, "ready:{$backendPid}\n");
fflush(STDOUT);

try {
    $mutation();
} catch (HttpException $exception) {
    if ($exception->getStatusCode() !== 403) {
        throw $exception;
    }

    fwrite(STDOUT, "result:forbidden\n");
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage()."\n");
    exit(1);
}

fwrite(STDOUT, "result:success\n");
