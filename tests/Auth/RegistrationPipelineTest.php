<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Tests\Auth;

use Codenzia\FilamentPanelBase\Auth\Services\RegistrationPipeline;
use Codenzia\FilamentPanelBase\Auth\Settings\AuthenticationSettings;
use Codenzia\FilamentPanelBase\Contracts\HasModerationStatus;
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

        // A host user table WITHOUT a `status` column — the common case for
        // apps that never opted into moderation.
        Schema::create('pipeline_plain_users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->default('secret');
            $table->timestamps();
        });
    }

    public function test_does_not_inject_status_for_models_without_the_moderation_contract(): void
    {
        // PNB-016: forcing `status` onto a model whose table has no such column
        // (and which does not implement HasModerationStatus) previously blew up
        // registration. The pipeline must now skip the injection entirely.
        $pipeline = new RegistrationPipeline(
            $this->moderatedSettings('moderated'),
        );

        $user = $pipeline->register(
            PipelinePlainUser::class,
            ['name' => 'Nia', 'email' => 'nia@test'],
        );

        $this->assertInstanceOf(PipelinePlainUser::class, $user);
        $this->assertSame('nia@test', $user->email);
        $this->assertFalse(array_key_exists('status', $user->getAttributes()));
    }

    public function test_injects_pending_status_for_moderated_contract_models(): void
    {
        // PNB-016: models that DO opt into moderation still get their status set.
        $pipeline = new RegistrationPipeline(
            $this->moderatedSettings('moderated'),
        );

        $user = $pipeline->register(
            PipelineModeratedUser::class,
            ['name' => 'Omar', 'email' => 'omar@test'],
        );

        $this->assertSame('pending', $user->status);
    }

    public function test_injects_approved_status_for_moderated_contract_models_in_open_mode(): void
    {
        $pipeline = new RegistrationPipeline(
            $this->moderatedSettings('open'),
        );

        $user = $pipeline->register(
            PipelineModeratedUser::class,
            ['name' => 'Pia', 'email' => 'pia@test'],
        );

        $this->assertSame('approved', $user->status);
    }

    private function moderatedSettings(string $mode): AuthenticationSettings
    {
        $settings = $this->settingsStub(AuthenticationSettings::class);
        $settings->registration_mode = $mode;

        return $settings;
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

class PipelinePlainUser extends AuthUser
{
    protected $table = 'pipeline_plain_users';

    protected $guarded = [];
}

class PipelineModeratedUser extends AuthUser implements HasModerationStatus
{
    protected $table = 'pipeline_test_users';

    protected $guarded = [];

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
