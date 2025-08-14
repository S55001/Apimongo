<?php

namespace App\Models;

use MongoDB\Laravel\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'users';
    protected $primaryKey = '_id';

    protected $fillable = ['name','email','password','role','api_token'];
    protected $hidden   = ['password','remember_token','api_token'];

    protected $casts = [
        '_id'               => 'string',
        'email_verified_at' => 'datetime',
    ];
}

