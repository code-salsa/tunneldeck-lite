<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomDomain extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'domain',
        'validation_status',
        'validated_at',
    ];

    protected $dates = [
        'validated_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tunnels()
    {
        return $this->hasMany(Tunnel::class, 'custom_domain_id');
    }
}
?>
