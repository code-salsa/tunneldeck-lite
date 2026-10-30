<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tunnel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'subdomain',
        'protocol',
        'port',
        'target_host',
        'public_url',
        'status',
        'connections_count',
        'last_active_at',
    ];

    protected $casts = [
        'port' => 'integer',
        'connections_count' => 'integer',
        'last_active_at' => 'datetime',
    ];
}
