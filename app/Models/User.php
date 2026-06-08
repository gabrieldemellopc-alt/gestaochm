<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
     protected $fillable = [
        'tenant_id','active',
    ];
    protected $casts = [
        'active' => 'boolean',
    ];
    public function divisionAccesses()
    {
        return $this->hasMany(
            UserDivisionAccess::class
        );
    }
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    public function checklistExecutions()
    {
        return $this->hasMany(
            ChecklistExecution::class
        );
    }
    public function divisions()
    {
        return $this->belongsToMany(Division::class);
    }
}
