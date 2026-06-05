<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Tests\Auth;

use Codenzia\FilamentPanelBase\Auth\Services\RegistrationPipeline;
use Codenzia\FilamentPanelBase\Auth\Settings\AuthenticationSettings;
use Codenzia\FilamentPanelBase\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RegistrationPipelineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('pipeline_test_users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->default('secret');
            $table->string('status')->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();
        });
    }

    public function test_register_creates_user_without_closure(): void
    {
        $pipeline = new RegistrationPipeline(
            $this->settingsStub(AuthenticationSettings::class),
        );

        $user = $pipeline->register(
            PipelineTestUser::class,
            ['name' => 'Alice', 'email' => 'a@test'],
        );

        $this->assertInstanceOf(PipelineTestUser::class, $user);
        $this->assertSame('a@test', $user->email);
        $this->assertNull($user->tenant_id);
    }

    public function test_before_user_creation_closure_can_mutate_payload(): void
    {
        $pipeline = new RegistrationPipeline(
            $this->settingsStub(AuthenticationSettings::class),
        );

        $user = $pipeline->register(
            PipelineTestUser::class,
            ['name' => 'Bob', 'email' => 'b@test'],
            beforeUserCreation: fn (array $payload): array => [...$payload, 'tenant_id' => 42],
        );

        $this->assertSame(42, $user->tenant_id);
    }

    public function test_closure_returning_non_array_leaves_payload_untouched(): void
    {
        $pipeline = new RegistrationPipeline(
            $this->settingsStub(AuthenticationSettings::class),
        );

        $user = $pipeline->register(
            PipelineTestUser::class,
            ['name' => 'Carol', 'email' => 'c@test'],
            beforeUserCreation: function (array $payload): void {
                // side-effect only, no return
            },
        );

        $this->assertNull($user->tenant_id);
    }

    public function test_closure_throwing_rolls_back_user_creation(): void
    {
        $pipeline = new RegistrationPipeline(
            $this->settingsStub(AuthenticationSettings::class),
        );

        try {
            $pipeline->register(
                PipelineTestUser::class,
                ['name' => 'Dave', 'email' => 'd@test'],
                beforeUserCreation: function (): void {
                    throw new \RuntimeException('signup aborted');
                },
            );
            $this->fail('Expected exception');
        } catch (\RuntimeException $e) {
            $this->assertSame('signup aborted', $e->getMessage());
        }

        $this->assertSame(0, DB::table('pipeline_test_users')->where('email', 'd@test')->count());
    }
}

class PipelineTestUser extends AuthUser
{
    protected $table = 'pipeline_test_users';

    protected $guarded = [];
}
