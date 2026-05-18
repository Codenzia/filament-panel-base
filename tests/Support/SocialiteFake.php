<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Tests\Support;

use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * Hand-rolled stand-in for a Socialite "remote user" payload — implements the
 * SDK's User contract so the trait under test can read every field through
 * the public interface (`getId`, `getEmail`, `getName`, ...). Public token
 * properties (`token`, `refreshToken`, `expiresIn`) match the real Socialite
 * shape used elsewhere in the trait.
 */
class SocialiteFake implements SocialiteUser
{
    public ?string $token = 'tok_abc';

    public ?string $refreshToken = 'tok_refresh';

    public ?int $expiresIn = 3600;

    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $id = 'soc_123',
        public readonly ?string $email = 'social@example.com',
        public readonly ?string $name = 'Social User',
        public readonly ?string $nickname = 'social',
        public readonly ?string $avatar = 'https://example.test/avatar.png',
        public readonly array $raw = ['email_verified' => true],
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getNickname(): ?string
    {
        return $this->nickname;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRaw(): array
    {
        return $this->raw;
    }
}
