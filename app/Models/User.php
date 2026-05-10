<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'must_change_password',
        'plain_password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'plain_password',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'must_change_password' => 'boolean',
    ];

    public function teacher()
    {
        return $this->hasOne(Teacher::class, 'user_id');
    }
    public function student()
    {
        return $this->hasOne(Student::class, 'user_id');
    }
}