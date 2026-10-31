<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tunnel extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'custom_domain_id',
        'custom_subdomain',
        'is_root_domain',
        'name',
        'port',
        'status',
        'public_url',
        'pid',
        'process_uuid',
        'last_started_at',
        'last_stopped_at',
    ];

    protected $casts = [
        'last_started_at' => 'datetime',
        'last_stopped_at' => 'datetime',
        'port' => 'integer',
        'pid' => 'integer',
        'is_root_domain' => 'boolean',
    ];

    /**
     * Start the tunnel process.
     */
    public function start(): bool
    {
        return app(\App\Services\TunnelManagerService::class)->startTunnel($this);
    }

    /**
     * Stop the tunnel process.
     */
    public function stop(): bool
    {
        return app(\App\Services\TunnelManagerService::class)->stopTunnel($this);
    }

    /**
     * Restart the tunnel process.
     */
    public function restart(): bool
    {
        return app(\App\Services\TunnelManagerService::class)->restartTunnel($this);
    }

    /**
     * Check if the tunnel is in running state.
     */
    public function isRunning(): bool
    {
        return $this->status === 'running' && !empty($this->pid) && app(\App\Services\ProcessTracker::class)->isPidAlive($this->pid);
    }

    /**
     * Refresh and return the current status of the tunnel.
     */
    public function refreshStatus(): string
    {
        return app(\App\Services\TunnelManagerService::class)->getTunnelStatus($this);
    }

    /**
     * Get the custom domain associated with the tunnel.
     */
    public function customDomain()
    {
        return class_exists(CustomDomain::class) ? $this->belongsTo(CustomDomain::class) : null;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
