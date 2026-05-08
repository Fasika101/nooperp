<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasAvatar
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_MANAGER = 'manager';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'branch_id',
        'password',
        'avatar_url',
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

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN) || $this->roles()->exists();
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * All branches this user is allowed to work in (many-to-many via branch_user pivot).
     *
     * @return BelongsToMany<Branch, $this>
     */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'branch_user');
    }

    /**
     * IDs of all branches this user is restricted to. Returns an empty array for unrestricted users.
     *
     * @return list<int>
     */
    public function branchIds(): array
    {
        if (! $this->isBranchRestricted()) {
            return [];
        }

        return $this->branches()->pluck('branches.id')->map(fn ($id) => (int) $id)->all();
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    /**
     * Users who may work across all branches (POS, transfers, reports) regardless of assigned branch_id.
     */
    public function hasUnrestrictedBranchAccess(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN)
            || $this->hasRole(self::ROLE_MANAGER);
    }

    public function isBranchRestricted(): bool
    {
        return ! $this->hasUnrestrictedBranchAccess() && $this->branches()->exists();
    }

    /**
     * Primary branch for this user: first assigned branch, or legacy branch_id, or null.
     * Used as default value in forms.
     */
    public function primaryBranchId(): ?int
    {
        if ($this->isBranchRestricted()) {
            $first = $this->branches()->orderBy('branches.id')->value('branches.id');

            return $first ? (int) $first : null;
        }

        return filled($this->branch_id) ? (int) $this->branch_id : null;
    }

    public function getFilamentAvatarUrl(): ?string
    {
        $path = $this->avatar_url;

        if (blank($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }
}
