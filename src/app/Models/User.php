<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'city',
        'country',
        'package',
        'currency',
        'is_onboarded',
        'license_synced_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_onboarded' => 'boolean',
            'license_synced_at' => 'datetime',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return true;
    }

    public function tunnelsEnabled(): bool
    {
        return true;
    }

    public function maxTunnels(): int
    {
        return match(strtolower($this->package ?? 'lite')) {
            'pro' => 50,
            'enterprise' => 500,
            default => 10,
        };
    }

    public function domainsEnabled(): bool
    {
        return true;
    }

    public function tunnels()
    {
        return $this->hasMany(Tunnel::class);
    }

    public function activities()
    {
        return $this->hasMany(Activity::class);
    }

    public function customDomains()
    {
        return $this->hasMany(CustomDomain::class);
    }
}
