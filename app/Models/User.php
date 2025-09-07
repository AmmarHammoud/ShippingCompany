<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'phone',
        'role',
        'center_id',
        'is_approved',
        'active',
        'latitude',
        'longitude',
        'verification_code',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_approved' => 'boolean',
        'active' => 'boolean',
    ];
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }
    public function center()
    {
        return $this->belongsTo(Center::class);
    }

// الشحنات التي أرسلها المستخدم
    public function shipmentsSent()
{
    return $this->hasMany(Shipment::class, 'client_id');
}

// الشحنات التي يقودها السائق
   public function shipmentsAssigned()
{
    return $this->hasMany(Shipment::class, 'driver_id');
}

    public function shipmentOffers()
    {
        return $this->hasMany(ShipmentDriverOffer::class, 'driver_id');
    }

    public function ratings()
    {
        return $this->hasMany(Rating::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isCenterManager(): bool
    {
        return $this->role === 'center_manager';
    }

    public function isDriver(): bool
    {
        return $this->role === 'driver';
    }

    public function isClient(): bool
    {
        return $this->role === 'client';
    }
}
