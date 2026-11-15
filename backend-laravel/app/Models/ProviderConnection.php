<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class ProviderConnection extends Model
{
    use HasUuids;

    protected $table = 'provider_connections';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'workspace_id',
        'provider',
        'api_key_encrypted',
        'auth_mode',
        'enabled',
        'default_model',
        'allowed_models',
        'created_by_user_id',
    ];

    protected $hidden = [
        'api_key_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'allowed_models' => 'array',
        ];
    }

    /**
     * Intercept api_key_encrypted to guarantee AES-256 encryption at rest
     * while gracefully handling legacy plaintext without throwing DecryptException.
     */
    protected function apiKeyEncrypted(): Attribute
    {
        return Attribute::make(
            get: function (?string $value): ?string {
                if ($value === null || $value === '') {
                    return null;
                }
                try {
                    return Crypt::decryptString($value);
                } catch (DecryptException) {
                    return $value;
                }
            },
            set: function (?string $value): ?string {
                if ($value === null || $value === '') {
                    return null;
                }
                return Crypt::encryptString(trim($value));
            }
        );
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
