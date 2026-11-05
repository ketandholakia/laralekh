<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Domain\Masters\Models\Company;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use Notifiable, HasApiTokens, HasRoles;

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class);
    }

    public function isMemberOf(int $companyId): bool
    {
        return $this->companies()->whereKey($companyId)->exists();
    }

    // Spatie caches role/permission relations per team id on the loaded user,
    // and its team filter only applies when the relation query runs. Dropping
    // the loaded relations forces the next permission check to re-query under
    // the new company team; never replace them with empty collections, which
    // would mark them as loaded and freeze a stale (possibly empty) verdict.
    public function forgetCachedPermissionRelations(): void
    {
        $this->unsetRelation('roles')->unsetRelation('permissions');
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
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
            'password' => 'hashed',
        ];
    }
}
