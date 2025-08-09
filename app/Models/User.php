<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;


class User extends Authenticatable
{
        use Notifiable;

        protected $connection = 'mongodb';
        protected $collection = 'users'; // opcional, por claridad
        protected $primaryKey = '_id';

        protected $fillable = ['name','email','password'];
        protected $hidden   = ['password','remember_token'];

        // Para que el _id regrese como string (útil en JSON)
        protected $casts = ['_id' => 'string'];
}
    
