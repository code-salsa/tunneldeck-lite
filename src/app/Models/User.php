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
        'email_verified_at',
        'password',
        'role',
        'city',
        'country',
        'package',
        'currency',
        'status',
        'is_onboarded',
        'license_synced_at',
        'coffee_donated_at',
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
            'coffee_donated_at' => 'datetime',
        ];
    }

    /**
     * Determine whether to show the "Buy me a coffee" card.
     * - If upgraded to pro (or any non-lite subscription): do not show at all.
     * - If user has already paid for coffee in the last 30 days: do not show.
     */
    public function shouldShowCoffeeCard(): bool
    {
        if ($this->isPro()) {
            return false;
        }

        // 1. Check explicit column
        if ($this->coffee_donated_at && $this->coffee_donated_at->greaterThan(now()->subDays(30))) {
            return false;
        }

        // 2. Check local activities table for any coffee donation within 30 days
        $recentDonation = Activity::where('user_id', $this->id)
            ->where(function ($q) {
                $q->where('description', 'like', '%coffee donation%')
                  ->orWhere('description', 'like', '%coffee%');
            })
            ->where('created_at', '>=', now()->subDays(30))
            ->latest('created_at')
            ->first();

        if ($recentDonation) {
            $this->coffee_donated_at = $recentDonation->created_at;
            $this->saveQuietly();
            return false;
        }

        return true;
    }

    /**
     * Determine if the user has previously donated coffee (even if >30 days ago).
     */
    public function hasPreviouslyDonatedCoffee(): bool
    {
        if ($this->coffee_donated_at) {
            return true;
        }

        return Activity::where('user_id', $this->id)
            ->where(function ($q) {
                $q->where('description', 'like', '%coffee donation%')
                  ->orWhere('description', 'like', '%coffee%');
            })
            ->exists();
    }

    /**
     * Dynamic message for the Buy Me a Coffee card:
     * - Next month (returning donor): "Last month I really enjoyed my coffee that you have paid for. Share another coffee that will help me concentrate on further development"
     * - First-time prompt: "Enjoying TunnelDeck Lite? Support development with a small coffee!"
     */
    public function getCoffeeCardMessage(): string
    {
        if ($this->hasPreviouslyDonatedCoffee()) {
            return 'Last month I really enjoyed my coffee that you have paid for. Share another coffee that will help me concentrate on further development';
        }

        return 'Enjoying TunnelDeck Lite? Support development with a small coffee!';
    }

    public function isSuspended(): bool
    {
        return strtolower((string) ($this->status ?? 'active')) === 'suspended';
    }

    public function isActive(): bool
    {
        return !$this->isSuspended();
    }

    public function isSuperAdmin(): bool
    {
        return true;
    }

    public function tunnelsEnabled(): bool
    {
        return $this->isActive();
    }

    public function isPro(): bool
    {
        if ($this->isSuspended()) {
            return false;
        }

        if (!in_array(strtolower($this->package ?? 'lite'), ['pro', 'enterprise'])) {
            return false;
        }

        return app(\App\Services\LicenseSignatureVerifier::class)->isUserProVerified($this);
    }

    public function isUnlimitedTunnels(): bool
    {
        return $this->isPro();
    }

    public function canCreateMoreTunnels(?int $limit = null): bool
    {
        if ($this->isUnlimitedTunnels()) {
            return true;
        }

        $max = $limit ?? $this->maxTunnels();
        if ($max < 0) {
            return true;
        }

        $count = $this->tunnels()->count();
        return $count < $max;
    }

    public function maxTunnels(): int
    {
        if (!$this->isPro()) {
            return 10;
        }

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

    /**
     * Determine if user is in platform exploration / simulation / demo mode.
     */
    public function isExploring(): bool
    {
        if ($this->is_onboarded) {
            return false;
        }

        return session('is_exploring', false)
            || \App\Models\Setting::get('user_' . $this->id . '_is_exploring') === 'true'
            || \App\Models\Tunnel::where('is_dummy', true)->exists();
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
        return class_exists(CustomDomain::class) ? $this->hasMany(CustomDomain::class) : null;
    }
}
