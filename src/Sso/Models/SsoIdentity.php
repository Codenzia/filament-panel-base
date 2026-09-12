<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Sso\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One linked OIDC identity: the `sub` claim a provider issued for a local
 * user, scoped by the issuer that minted it — re-pointing a provider config
 * entry at a different IdP therefore starts a fresh identity rather than
 * inheriting the old one's user. Deliberately holds no tokens — the SSO module exchanges the code,
 * reads the id_token claims and discards both, so there is nothing here for
 * a database dump to leak.
 */
class SsoIdentity extends Model
{
    protected $table = 'sso_identities';

    protected $fillable = [
        'user_id',
        'provider',
        'issuer',
        'subject',
        'last_login_at',
    ];

    protected $casts = [
        'last_login_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            config('filament-panel-base.user_model', User::class),
            'user_id'
        );
    }
}
