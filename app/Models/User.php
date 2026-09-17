<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['role_id', 'first_name', 'last_name', 'email', 'password_hash', 'phone', 'is_active'])]
#[Hidden(['password_hash'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $table = 'users';

    protected $primaryKey = 'user_id';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'password_hash' => 'hashed',
        ];
    }

    /**
     * The legacy schema stores the hash in `password_hash`, not `password`,
     * so the Authenticatable contract is pointed at that column instead.
     */
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    /**
     * No remember_token column exists on the legacy `users` table.
     */
    public function getRememberToken()
    {
        return null;
    }

    public function setRememberToken($value)
    {
        // no-op: legacy schema has no remember_token column
    }

    public function getRememberTokenName()
    {
        return '';
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id', 'role_id');
    }

    public function client()
    {
        return $this->hasOne(Client::class, 'user_id', 'user_id');
    }
}
