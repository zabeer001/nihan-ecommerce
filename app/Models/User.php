<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Spatie\Permission\Models\Role;

class User extends Authenticatable implements JWTSubject

{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'role',
        'avatar_path',
        'is_frozen',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_frozen' => 'boolean',
            'password' => 'hashed',
        ];
    }
    public function isSoftwareEngineer(): bool
    {
        return in_array(strtolower(trim((string) $this->email)), config('rbac.software_engineer_emails', []), true);
    }

    public function canAssignRole(Role $role): bool
    {
        return $this->isSoftwareEngineer()
            || ($role->name !== 'superadmin' && ($role->name !== 'admin' || $this->role === 'superadmin'));
    }

    public function canChangeRoleOf(User $user): bool
    {
        if ($user->isSoftwareEngineer() || $user->role === 'software_engineer') {
            return false;
        }

        if ($this->isSoftwareEngineer()) {
            return true;
        }

        return ! $this->is($user)
            && $user->role !== 'superadmin'
            && ($user->role !== 'admin' || $this->role === 'superadmin');
    }

    public function canDeleteUser(User $user): bool
    {
        return ! $this->is($user)
            && ! $user->isSoftwareEngineer()
            && $this->canChangeRoleOf($user);
    }

    /** @return list<string> */
    public function permissionNames(): array
    {
        if ($this->isSoftwareEngineer()) {
            return \Spatie\Permission\Models\Permission::query()->where('guard_name', 'api')->orderBy('name')->pluck('name')->all();
        }

        $role = Role::query()->where('guard_name', 'api')->where('name', $this->role)->first();

        return $role && $role->name === $this->role
            ? $role->permissions()->orderBy('name')->pluck('name')->all()
            : [];
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

  

}
