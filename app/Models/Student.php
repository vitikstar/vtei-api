<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Student extends Authenticatable
{
    use HasApiTokens;

    protected $connection = 'mysql';
    protected $table = 'users_student';

    protected $fillable = [
        'name', 'email', 'pwd', 'photo_url', 'gender',
        'home_address1', 'status', 'role_code', 'userpic',
    ];

    protected $hidden = ['pwd', 'auth_key', 'access_token'];

    protected $casts = [
        'status' => 'integer',
        'role_code' => 'integer',
        'gender' => 'integer',
    ];

    public function getAuthPassword(): string
    {
        return $this->pwd;
    }

    public function groups()
    {
        return $this->hasMany(StudentGroup::class, 'student_id', 'id');
    }

    public function isActive(): bool
    {
        return $this->status === 2;
    }
}
