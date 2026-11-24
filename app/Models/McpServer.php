<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A third-party MCP server the AI copilot may call.
 *
 * Servers are per school, so one tenant's configured integrations and
 * credentials are never visible to another. `enabled_tools` is the real
 * control on what the model can reach: an empty allowlist exposes nothing, so
 * adding a server does not hand its whole catalog to the model automatically.
 */
final class McpServer extends Model
{
    use BelongsToSchool;

    public const string TRANSPORT_WEB = 'web';

    public const string TRANSPORT_LOCAL = 'local';

    public const string AUTH_NONE = 'none';

    public const string AUTH_BEARER = 'bearer';

    public const string AUTH_OAUTH = 'oauth';

    protected $fillable = [
        'public_id',
        'school_id',
        'name',
        'description',
        'transport',
        'url',
        'command',
        'command_arguments',
        'auth_type',
        'credentials',
        'enabled_tools',
        'allowed_agents',
        'is_active',
        'timeout_seconds',
        'cache_ttl_seconds',
        'last_connected_at',
        'last_error',
    ];

    protected $hidden = [
        'credentials',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Whether this server is configured well enough to connect.
     */
    public function isUsable(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return match ($this->transport) {
            self::TRANSPORT_WEB => filled($this->url),
            self::TRANSPORT_LOCAL => filled($this->command),
            default => false,
        };
    }

    /**
     * Whether the given tool is on this server's allowlist.
     */
    public function exposesTool(string $tool): bool
    {
        return in_array($tool, $this->enabled_tools ?? [], true);
    }

    /**
     * Whether the given agent may see this server's tools.
     *
     * An empty agent list means the server is configured but has not been
     * pointed at any agent yet, so it exposes nothing.
     */
    public function visibleToAgent(string $agent): bool
    {
        return in_array($agent, $this->allowed_agents ?? [], true);
    }

    /**
     * The configured bearer token, if any.
     */
    public function bearerToken(): ?string
    {
        $token = $this->credentials['token'] ?? null;

        return is_string($token) && filled($token) ? $token : null;
    }

    protected static function booted(): void
    {
        self::creating(function (self $server): void {
            $server->public_id ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'command_arguments' => 'array',
            'enabled_tools' => 'array',
            'allowed_agents' => 'array',
            'is_active' => 'boolean',
            'timeout_seconds' => 'integer',
            'cache_ttl_seconds' => 'integer',
            'last_connected_at' => 'datetime',
        ];
    }
}
