<?php

declare(strict_types=1);

namespace Tests\Feature\Contract;

use App\OpenApi\ContractDocumentTransformer;
use App\OpenApi\ContractOperationOverlay;
use Dedoc\Scramble\Support\Generator\InfoObject;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Path;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\Generator\SecuritySchemes\ApiKeySecurityScheme;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Tests\TestCase;

/**
 * Exercita os ramos defensivos do transformador e do overlay de contrato que o export
 * oficial (LC_ALL=C) nunca alcança, montando documentos sintéticos aqui mesmo.
 *
 * O snapshot oficial segue main-only: nada gerado por este teste é persistido nem
 * comparado com `contracts/openapi.json`.
 */
final class OpenApiCoverageW6Test extends TestCase
{
    public function test_logical_version_replaces_placeholder_and_empty_versions(): void
    {
        $transformer = new ContractDocumentTransformer;

        $placeholder = $this->document(version: '0.0.1');
        $transformer($placeholder);
        $this->assertSame(ContractDocumentTransformer::LOGICAL_VERSION, $placeholder->info->version);

        $empty = $this->document(version: '');
        $transformer($empty);
        $this->assertSame(ContractDocumentTransformer::LOGICAL_VERSION, $empty->info->version);
        $this->assertSame('Starter Kit API', $empty->info->title);

        $explicit = $this->document(version: '2026.10');
        $transformer($explicit);
        $this->assertSame('2026.10', $explicit->info->version);
    }

    public function test_legacy_configured_session_cookie_name_is_replaced(): void
    {
        config(['session.cookie' => 'laravel-session']);

        $legacy = $this->document();
        (new ContractDocumentTransformer)($legacy);

        $this->assertSame('starter-kit-api-session', $this->sessionCookieName($legacy));

        config(['session.cookie' => 'spa-session']);

        $configured = $this->document();
        (new ContractDocumentTransformer)($configured);

        $this->assertSame('spa-session', $this->sessionCookieName($configured));
    }

    public function test_http_and_open_id_schemes_are_dropped_and_api_key_schemes_kept(): void
    {
        $document = $this->document();
        $document->components->securitySchemes['bearerAuth'] = $this->scheme(SecurityScheme::http('bearer'));
        $document->components->securitySchemes['legacyOauth'] = $this->scheme(SecurityScheme::oauth2());
        $document->components->securitySchemes['legacyOidc'] = $this->scheme(SecurityScheme::openIdConnect('https://example.test/.well-known/openid-configuration'));
        $document->components->securitySchemes['legacyApiKey'] = $this->scheme(SecurityScheme::apiKey('header', 'X-Legacy-Key'));

        (new ContractDocumentTransformer)($document);

        $this->assertSame(['legacyApiKey', 'sessionCookie', 'xsrfToken'], array_keys($document->components->securitySchemes));
        $this->assertSame([], $document->security);
    }

    public function test_resource_schema_fixes_skip_absent_or_non_object_schemas(): void
    {
        $absent = $this->document();
        $absent->components->schemas['ActivityResource'] = $this->schema(new ObjectType);

        (new ContractDocumentTransformer)($absent);

        $this->assertArrayNotHasKey('UserResource', $absent->components->schemas);
        $this->assertArrayNotHasKey('RoleResource', $absent->components->schemas);
        $this->assertSame([], $this->objectType($absent, 'ActivityResource')->properties);

        $present = $this->document();
        $user = new ObjectType;
        $user->addProperty('email_verified_at', new StringType);
        $user->setRequired(['email_verified_at', 'role_display_names']);
        $present->components->schemas['UserResource'] = $this->schema($user);
        $activity = new ObjectType;
        $activity->addProperty('created_at', new StringType);
        $present->components->schemas['ActivityResource'] = $this->schema($activity);
        $role = new ObjectType;
        $role->addProperty('display_name', new StringType);
        $role->addProperty('permissions', new ArrayType);
        $role->setRequired(['display_name', 'permissions']);
        $present->components->schemas['RoleResource'] = $this->schema($role);

        (new ContractDocumentTransformer)($present);

        $userType = $this->objectType($present, 'UserResource');
        $this->assertTrue($userType->hasProperty('role_display_names'));
        $this->assertSame(['email_verified_at'], $userType->required);

        $verifiedAt = $userType->getProperty('email_verified_at');
        $this->assertInstanceOf(StringType::class, $verifiedAt);
        $this->assertSame('date-time', $verifiedAt->format);
        $this->assertTrue($verifiedAt->nullable);

        $createdAt = $this->objectType($present, 'ActivityResource')->getProperty('created_at');
        $this->assertInstanceOf(StringType::class, $createdAt);
        $this->assertSame('date-time', $createdAt->format);

        $roleType = $this->objectType($present, 'RoleResource');
        $this->assertSame(['permissions'], $roleType->required);
        $permissions = $roleType->getProperty('permissions');
        $this->assertInstanceOf(ArrayType::class, $permissions);
        $this->assertInstanceOf(StringType::class, $permissions->items);
    }

    public function test_paginated_operation_without_object_envelope_is_left_untouched(): void
    {
        $document = $this->document();

        $plainSchema = $this->schema(new StringType);
        $plain = $this->jsonResponseWithSchema(200, $plainSchema);
        $empty = $this->response(200);

        $plainOperation = $this->operation('get');
        $plainOperation->responses = [$plain, $empty];
        $document->paths[] = $this->path('api/v1/users', $plainOperation);

        $envelopeSchema = $this->schema($this->dataEnvelope());
        $envelope = $this->jsonResponseWithSchema(200, $envelopeSchema);
        $envelopeOperation = $this->operation('get');
        $envelopeOperation->responses = [$envelope];
        $document->paths[] = $this->path('api/v1/roles', $envelopeOperation);

        (new ContractDocumentTransformer)($document);

        $this->assertSame($plainSchema, $plain->content['application/json']);
        $this->assertInstanceOf(StringType::class, $plainSchema->type);
        $this->assertSame([], $empty->content);

        $paginatedType = $envelopeSchema->type;
        $this->assertInstanceOf(ObjectType::class, $paginatedType);
        $this->assertTrue($paginatedType->hasProperty('links'));
        $this->assertTrue($paginatedType->hasProperty('meta'));
    }

    public function test_existing_csrf_cookie_path_is_preserved(): void
    {
        $document = $this->document();
        $existingOperation = $this->operation('get');
        $existingPath = $this->path('/sanctum/csrf-cookie', $existingOperation);
        $document->paths[] = $existingPath;

        (new ContractDocumentTransformer)($document);

        $this->assertCount(1, $document->paths);
        $this->assertSame($existingPath, $document->paths[0]);
        $this->assertNull($existingOperation->operationId);
        $this->assertSame('', $existingOperation->description);
        $this->assertSame([], $existingOperation->responses);

        $injected = $this->document();
        (new ContractDocumentTransformer)($injected);

        $this->assertCount(1, $injected->paths);
        $csrfPath = $injected->paths[0];
        $this->assertSame('sanctum/csrf-cookie', $csrfPath->path);
        $injectedOperation = $csrfPath->operations['get'];
        $this->assertSame('sanctum.csrfCookie', $injectedOperation->operationId);
        $this->assertSame(['204'], $this->responseCodes($injectedOperation->responses));
    }

    public function test_overlay_creates_response_list_when_operation_declares_none(): void
    {
        $document = $this->document();
        $operation = $this->operation('post');
        $operation->responses = null;
        $document->paths[] = $this->path('api/v1/login', $operation);

        (new ContractOperationOverlay)->apply($document);

        $this->assertSame(['401', '403', '419', '429'], $this->responseCodes($operation->responses));
    }

    public function test_overlay_does_not_duplicate_xsrf_header_parameter(): void
    {
        $document = $this->document();
        $operation = $this->operation('post');
        $existing = $this->parameter(ContractDocumentTransformer::XSRF_HEADER, 'header');
        $operation->parameters[] = $existing;
        $document->paths[] = $this->path('api/v1/login', $operation);

        (new ContractOperationOverlay)->apply($document);

        $this->assertSame([$existing], $operation->parameters);

        $control = $this->document();
        $controlOperation = $this->operation('post');
        $controlOperation->parameters[] = $this->parameter(ContractDocumentTransformer::XSRF_HEADER, 'query');
        $control->paths[] = $this->path('api/v1/login', $controlOperation);

        (new ContractOperationOverlay)->apply($control);

        $this->assertCount(2, $controlOperation->parameters);
        $header = $this->parameterAt($controlOperation, 1);
        $this->assertSame('header', $header->in);
        $this->assertTrue($header->required);
    }

    public function test_overlay_does_not_duplicate_verification_query_parameters(): void
    {
        $document = $this->document();
        $operation = $this->operation('get');
        $expires = $this->parameter('expires', 'query');
        $signature = $this->parameter('signature', 'query');
        $operation->parameters = [$expires, $signature];
        $document->paths[] = $this->path('api/v1/email/verify/{id}/{hash}', $operation);

        (new ContractOperationOverlay)->apply($document);

        $this->assertSame([$expires, $signature], $operation->parameters);

        $control = $this->document();
        $controlOperation = $this->operation('get');
        $controlOperation->parameters = [$this->parameter('expires', 'header'), $this->parameter('signature', 'header')];
        $control->paths[] = $this->path('api/v1/email/verify/{id}/{hash}', $controlOperation);

        (new ContractOperationOverlay)->apply($control);

        $this->assertCount(4, $controlOperation->parameters);
        $added = $this->parameterAt($controlOperation, 3);
        $this->assertSame('signature', $added->name);
        $this->assertSame('query', $added->in);
        $this->assertFalse($added->required);
    }

    public function test_overlay_ignores_session_flag_schema_on_non_200_responses(): void
    {
        $skippedItems = new ObjectType;
        $skippedItems->addProperty('is_current', new StringType);

        $this->applyOverlayToSessionsResponse($this->jsonResponse(422, $this->sessionsEnvelope($skippedItems)));

        $skipped = $skippedItems->getProperty('is_current');
        $this->assertInstanceOf(StringType::class, $skipped);

        $flaggedItems = new ObjectType;
        $flaggedItems->addProperty('is_current', new StringType);

        $this->applyOverlayToSessionsResponse($this->jsonResponse(200, $this->sessionsEnvelope($flaggedItems)));

        $flagged = $flaggedItems->getProperty('is_current');
        $this->assertInstanceOf(BooleanType::class, $flagged);
    }

    public function test_overlay_ignores_session_flag_schema_without_object_envelope(): void
    {
        $scalar = $this->schema(new StringType);

        $this->applyOverlayToSessionsResponse($this->jsonResponseWithSchema(200, $scalar));

        $this->assertInstanceOf(StringType::class, $scalar->type);

        $dataLess = new ObjectType;
        $dataLess->addProperty('total', new StringType);

        $this->applyOverlayToSessionsResponse($this->jsonResponse(200, $dataLess));

        $this->assertFalse($dataLess->hasProperty('is_current'));
        $this->assertSame(['total'], array_keys($dataLess->properties));
    }

    public function test_overlay_ignores_session_flag_schema_when_data_is_not_a_session_collection(): void
    {
        $scalarData = new ObjectType;
        $scalarData->addProperty('data', new StringType);

        $this->applyOverlayToSessionsResponse($this->jsonResponse(200, $scalarData));

        $scalarDataProperty = $scalarData->getProperty('data');
        $this->assertInstanceOf(StringType::class, $scalarDataProperty);

        $stringItems = new ArrayType;
        $stringItems->setItems(new StringType);
        $stringItemsEnvelope = new ObjectType;
        $stringItemsEnvelope->addProperty('data', $stringItems);

        $this->applyOverlayToSessionsResponse($this->jsonResponse(200, $stringItemsEnvelope));

        $this->assertInstanceOf(StringType::class, $stringItems->items);

        $unflaggedItems = new ObjectType;
        $unflaggedEnvelope = $this->sessionsEnvelope($unflaggedItems);

        $this->applyOverlayToSessionsResponse($this->jsonResponse(200, $unflaggedEnvelope));

        $this->assertFalse($unflaggedItems->hasProperty('is_current'));
    }

    private function document(string $version = '1.0.0'): OpenApi
    {
        $document = OpenApi::make('3.1.0');
        $this->assertInstanceOf(OpenApi::class, $document);
        $info = InfoObject::make('Starter Kit API');
        $this->assertInstanceOf(InfoObject::class, $info);
        $document->setInfo($info->setVersion($version));

        return $document;
    }

    private function applyOverlayToSessionsResponse(Response $response): void
    {
        $document = $this->document();
        $operation = $this->operation('get');
        $operation->responses = [$response];
        $document->paths[] = $this->path('api/v1/auth/sessions', $operation);

        (new ContractOperationOverlay)->apply($document);
    }

    private function path(string $path, Operation $operation): Path
    {
        $pathItem = Path::make($path);
        $this->assertInstanceOf(Path::class, $pathItem);
        $pathItem->addOperation($operation);

        return $pathItem;
    }

    private function operation(string $method): Operation
    {
        $operation = Operation::make($method);
        $this->assertInstanceOf(Operation::class, $operation);

        return $operation;
    }

    private function parameter(string $name, string $in): Parameter
    {
        $parameter = Parameter::make($name, $in);
        $this->assertInstanceOf(Parameter::class, $parameter);

        return $parameter;
    }

    private function parameterAt(Operation $operation, int $index): Parameter
    {
        $parameter = $operation->parameters[$index] ?? null;
        $this->assertInstanceOf(Parameter::class, $parameter);

        return $parameter;
    }

    private function schema(Type $payload): Schema
    {
        $schema = Schema::fromType($payload);
        $this->assertInstanceOf(Schema::class, $schema);

        return $schema;
    }

    private function scheme(mixed $scheme): SecurityScheme
    {
        $this->assertInstanceOf(SecurityScheme::class, $scheme);

        return $scheme;
    }

    private function response(int $status): Response
    {
        $response = Response::make($status);
        $this->assertInstanceOf(Response::class, $response);

        return $response;
    }

    private function jsonResponse(int $status, Type $payload): Response
    {
        return $this->jsonResponseWithSchema($status, $this->schema($payload));
    }

    private function jsonResponseWithSchema(int $status, Schema $schema): Response
    {
        $response = $this->response($status);
        $response->setContent('application/json', $schema);

        return $response;
    }

    /**
     * Envelope paginado mínimo: o único requisito do transformador é a propriedade `data`.
     */
    private function dataEnvelope(): ObjectType
    {
        $data = new ArrayType;
        $data->setItems(new StringType);

        $envelope = new ObjectType;
        $envelope->addProperty('data', $data);

        return $envelope;
    }

    /**
     * Envelope de sessões ativas: `data` é uma coleção de objetos.
     */
    private function sessionsEnvelope(ObjectType $items): ObjectType
    {
        $data = new ArrayType;
        $data->setItems($items);

        $envelope = new ObjectType;
        $envelope->addProperty('data', $data);

        return $envelope;
    }

    private function sessionCookieName(OpenApi $document): string
    {
        $scheme = $document->components->securitySchemes['sessionCookie'] ?? null;
        $this->assertInstanceOf(ApiKeySecurityScheme::class, $scheme);

        return $scheme->name;
    }

    private function objectType(OpenApi $document, string $schemaName): ObjectType
    {
        $schema = $document->components->schemas[$schemaName] ?? null;
        $this->assertInstanceOf(Schema::class, $schema);
        $type = $schema->type;
        $this->assertInstanceOf(ObjectType::class, $type);

        return $type;
    }

    /**
     * @param  array<array-key, mixed>|null  $responses
     * @return list<string>
     */
    private function responseCodes(?array $responses): array
    {
        $codes = [];
        foreach ($responses ?? [] as $response) {
            $this->assertInstanceOf(Response::class, $response);
            $codes[] = (string) $response->code;
        }

        return $codes;
    }
}
