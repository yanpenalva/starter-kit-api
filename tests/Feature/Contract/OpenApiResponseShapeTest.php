<?php

declare(strict_types=1);

namespace Tests\Feature\Contract;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class OpenApiResponseShapeTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_documents_paginated_envelopes_and_nullable_resource_fields(): void
    {
        $document = $this->exportDocument();
        $paths = $this->object($document['paths'] ?? null);

        foreach (['/api/v1/users', '/api/v1/roles', '/api/v1/activity-log'] as $path) {
            $pathItem = $this->object($paths[$path] ?? null);
            $get = $this->object($pathItem['get'] ?? null);
            $responses = $this->object($get['responses'] ?? null);
            $response = $this->object($responses['200'] ?? null);
            $content = $this->object($response['content'] ?? null);
            $json = $this->object($content['application/json'] ?? null);
            $schema = $this->object($json['schema'] ?? null);
            $properties = $this->object($schema['properties'] ?? null);
            $links = $this->object($properties['links'] ?? null);
            $linkProperties = $this->object($links['properties'] ?? null);
            $meta = $this->object($properties['meta'] ?? null);
            $metaProperties = $this->object($meta['properties'] ?? null);
            $metaLinks = $this->object($metaProperties['links'] ?? null);
            $metaLinkItems = $this->object($metaLinks['items'] ?? null);
            $metaLinkProperties = $this->object($metaLinkItems['properties'] ?? null);
            $metaLinkUrl = $this->object($metaLinkProperties['url'] ?? null);
            $metaLinkPage = $this->object($metaLinkProperties['page'] ?? null);
            $previous = $this->object($linkProperties['prev'] ?? null);
            $next = $this->object($linkProperties['next'] ?? null);
            $from = $this->object($metaProperties['from'] ?? null);
            $to = $this->object($metaProperties['to'] ?? null);

            $this->assertSame(['data'], $schema['required'] ?? null, $path);
            $this->assertSame(['first', 'last', 'prev', 'next'], $links['required'] ?? null, $path);
            $this->assertSame(['string', 'null'], $previous['type'] ?? null, $path);
            $this->assertSame(['string', 'null'], $next['type'] ?? null, $path);
            $this->assertSame(['integer', 'null'], $from['type'] ?? null, $path);
            $this->assertSame(['integer', 'null'], $to['type'] ?? null, $path);
            $this->assertSame(['url', 'label', 'active', 'page'], $metaLinkItems['required'] ?? null, $path);
            $this->assertSame(['string', 'null'], $metaLinkUrl['type'] ?? null, $path);
            $this->assertSame(['integer', 'null'], $metaLinkPage['type'] ?? null, $path);
            $this->assertSame(
                ['current_page', 'from', 'last_page', 'links', 'path', 'per_page', 'to', 'total'],
                $meta['required'] ?? null,
                $path,
            );
        }

        $auditGet = $this->object($this->object($paths['/api/v1/activity-log'] ?? null)['get'] ?? null);
        $auditParameters = $this->object($auditGet['parameters'] ?? null);
        $searchParameters = array_values(array_filter(
            $auditParameters,
            static fn (mixed $parameter): bool => is_array($parameter) && ($parameter['name'] ?? null) === 'search',
        ));
        $searchParameter = $this->object($searchParameters[0] ?? null);
        $searchSchema = $this->object($searchParameter['schema'] ?? null);
        $this->assertSame('query', $searchParameter['in'] ?? null);
        $this->assertSame(['string', 'null'], $searchSchema['type'] ?? null);
        $this->assertSame(255, $searchSchema['maxLength'] ?? null);

        $components = $this->object($document['components'] ?? null);
        $schemas = $this->object($components['schemas'] ?? null);
        $user = $this->object($schemas['UserResource'] ?? null);
        $userProperties = $this->object($user['properties'] ?? null);
        $roles = $this->object($userProperties['roles'] ?? null);
        $roleItems = $this->object($roles['items'] ?? null);
        $roleItemProperties = $this->object($roleItems['properties'] ?? null);
        $relations = $this->object($roleItemProperties['relations'] ?? null);
        $cpf = $this->object($userProperties['cpf'] ?? null);
        $roleDisplayNames = $this->object($userProperties['role_display_names'] ?? null);
        $userRequired = $user['required'] ?? null;
        $this->assertIsArray($userRequired);

        $role = $this->object($schemas['RoleResource'] ?? null);
        $roleProperties = $this->object($role['properties'] ?? null);
        $roleDisplayName = $this->object($roleProperties['display_name'] ?? null);
        $roleRequired = $role['required'] ?? null;
        $this->assertIsArray($roleRequired);
        $permissions = $this->object($roleProperties['permissions'] ?? null);
        $permissionItems = $this->object($permissions['items'] ?? null);
        $roleDisplayNameValues = $this->object($roleDisplayNames['additionalProperties'] ?? null);

        $this->assertSame('object', $roleItems['type'] ?? null);
        $this->assertSame(['relations'], $roleItems['required'] ?? null);
        $this->assertSame('array', $relations['type'] ?? null);
        $this->assertSame([['type' => 'string', 'const' => 'roles']], $relations['prefixItems'] ?? null);
        $this->assertSame(1, $relations['minItems'] ?? null);
        $this->assertSame(1, $relations['maxItems'] ?? null);
        $this->assertSame('string', $permissionItems['type'] ?? null);
        $this->assertSame('string', $roleDisplayName['type'] ?? null);
        $this->assertSame('object', $roleDisplayNames['type'] ?? null);
        $this->assertSame('string', $roleDisplayNameValues['type'] ?? null);
        $this->assertNotContains('role_display_names', $userRequired);
        $this->assertNotContains('display_name', $roleRequired);
        $this->assertSame(['string', 'null'], $cpf['type'] ?? null);
        $this->assertNotContains('cpf', $userRequired);

        $this->assertNullableDateTimeField($schemas['UserResource'] ?? null, 'email_verified_at');
        $this->assertNullableDateTimeField($schemas['UserResource'] ?? null, 'deactivated_at');
        $this->assertNullableDateTimeField($schemas['ActivityResource'] ?? null, 'created_at');
    }

    /**
     * @return array<array-key, mixed>
     */
    private function exportDocument(): array
    {
        $output = storage_path('app/openapi-response-shape-test.json');

        try {
            $this->assertSame(0, Artisan::call('scramble:export', ['--path' => $output]));
            $payload = file_get_contents($output);
            $this->assertIsString($payload);

            return $this->object(json_decode($payload, true));
        } finally {
            if (is_file($output)) {
                unlink($output);
            }
        }
    }

    /**
     * @return array<array-key, mixed>
     */
    private function object(mixed $value): array
    {
        $this->assertIsArray($value);

        return $value;
    }

    private function assertNullableDateTimeField(mixed $schema, string $field): void
    {
        $schema = $this->object($schema);
        $properties = $this->object($schema['properties'] ?? null);
        $property = $this->object($properties[$field] ?? null);

        $this->assertSame(['string', 'null'], $property['type'] ?? null, $field);
        $this->assertSame('date-time', $property['format'] ?? null, $field);
    }
}
