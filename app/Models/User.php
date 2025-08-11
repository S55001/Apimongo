<?php

namespace App\Models;

// 👇 OJO: usa el Auth User de Mongo, no el de Illuminate
use MongoDB\Laravel\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'users';
    protected $primaryKey = '_id';

    protected $fillable = ['name','email','password','role'];
    protected $hidden   = ['password','remember_token'];

    protected $casts = [
        '_id'               => 'string',
        'email_verified_at' => 'datetime',
    ];

    // JWT
    public function getJWTIdentifier()
    {
        return (string) $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return ['role' => $this->role, 'name' => $this->name];
    }
}
