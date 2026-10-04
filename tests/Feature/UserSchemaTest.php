<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Rules\Cpf;
use Database\Factories\UserFactory;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

final class UserSchemaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Guarded destructive operations target the isolated test database only.
        $database = config('database.connections.pgsql.database');
        $this->assertIsString($database);
        $this->assertSame('starter_api_test', $database);
    }

    public function test_verification_and_deactivation_are_independent_states(): void
    {
        $user = User::factory()->unverified()->deactivated()->create();

        $this->assertNull($user->email_verified_at);
        $this->assertNotNull($user->deactivated_at);

        $activeUnverified = User::factory()->unverified()->create();
        $this->assertNull($activeUnverified->email_verified_at);
        $this->assertNull($activeUnverified->deactivated_at);
    }

    public function test_multiple_users_without_cpf_are_valid(): void
    {
        User::factory()->count(3)->create();

        $this->assertSame(3, DB::table('users')->whereNull('cpf')->count());
    }

    public function test_cpf_is_stored_normalized_with_leading_zeros(): void
    {
        $user = User::factory()->withCpf('00000000191')->create();

        $this->assertSame('00000000191', $user->cpf);
        $this->assertSame('00000000191', DB::table('users')->value('cpf'));
    }

    public function test_duplicate_cpf_is_rejected_by_the_database(): void
    {
        $cpf = UserFactory::generateCpf();

        User::factory()->withCpf($cpf)->create();

        $this->expectException(QueryException::class);
        DB::table('users')->insert([
            'name' => 'Duplicate',
            'email' => 'duplicate@example.test',
            'password' => 'password',
            'cpf' => $cpf,
        ]);
    }

    public function test_concurrent_duplicate_cpf_insert_waits_on_the_unique_index(): void
    {
        $cpf = UserFactory::generateCpf();

        // RefreshDatabase keeps this connection inside a transaction, so the
        // inserted index entry is visible only to the constraint checks of a
        // second connection after it waits (or when this transaction ends).
        User::factory()->withCpf($cpf)->create();

        config(['database.connections.conc' => config('database.connections.pgsql')]);
        $concurrent = DB::connection('conc');
        $concurrent->statement("SET lock_timeout TO '500ms'");

        try {
            $concurrent->table('users')->insert([
                'name' => 'Concurrent',
                'email' => 'concurrent@example.test',
                'password' => 'password',
                'cpf' => $cpf,
            ]);
            $this->fail('The concurrent duplicate insert must not succeed.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString(
                'users_cpf_unique',
                (string) $exception->getMessage(),
            );
        }
    }

    public function test_cpf_value_rule_accepts_only_valid_normalized_digits(): void
    {
        $rule = ['cpf' => [new Cpf]];

        $this->assertTrue(Validator::make(['cpf' => null], $rule)->passes());
        $this->assertTrue(Validator::make(['cpf' => '529.982.247-25'], $rule)->passes());
        $this->assertTrue(Validator::make(['cpf' => '00000000191'], $rule)->passes());
        $this->assertFalse(Validator::make(['cpf' => '11111111111'], $rule)->passes());
        $this->assertFalse(Validator::make(['cpf' => '12345678901'], $rule)->passes());
        $this->assertFalse(Validator::make(['cpf' => '5299822472'], $rule)->passes());
        $this->assertFalse(Validator::make(['cpf' => 52998224725], $rule)->passes());
    }

    public function test_cpf_rule_rejections_carry_localized_ptbr_messages(): void
    {
        $cases = [
            [52998224725, 'O campo cpf deve ser uma string de 11 dígitos.'],
            ['5299822472', 'O campo cpf deve conter exatamente 11 dígitos.'],
            ['11111111111', 'O campo cpf não pode ter todos os dígitos repetidos.'],
            ['12345678901', 'Os dígitos verificadores do campo cpf são inválidos.'],
        ];

        foreach ($cases as [$value, $expectedMessage]) {
            $validation = Validator::make(['cpf' => $value], ['cpf' => [new Cpf]]);

            $this->assertFalse($validation->passes());
            $this->assertSame($expectedMessage, $validation->errors()->first('cpf'));
        }
    }

    public function test_formatted_cpf_input_is_normalized_before_persisting(): void
    {
        $user = User::factory()->withCpf('529.982.247-25')->create();

        $this->assertSame('52998224725', $user->cpf);
        $this->assertTrue(
            Validator::make(['cpf' => $user->cpf], ['cpf' => [new Cpf]])->passes(),
        );
    }

    public function test_sensitive_fields_are_hidden_from_arrays(): void
    {
        $user = User::factory()->withCpf()->create();

        $visible = $user->toArray();

        $this->assertArrayNotHasKey('password', $visible);
        $this->assertArrayNotHasKey('remember_token', $visible);
        $this->assertArrayNotHasKey('cpf', $visible);

        $json = (array) $user->jsonSerialize();

        $this->assertArrayNotHasKey('password', $json);
        $this->assertArrayNotHasKey('remember_token', $json);
        $this->assertArrayNotHasKey('cpf', $json);
    }
}
