<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Auth\LoginProvider;
use App\Models\Customer\CustomerAddress;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Ladder\HasRoles;
use Ladder\Ladder;
use Laravel\Sanctum\HasApiTokens;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class User extends Authenticatable implements FilamentUser, HasMedia
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, SoftDeletes, CanResetPassword, MustVerifyEmail, Authorizable, HasApiTokens, InteractsWithMedia;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'email_verified_at',
        'password',
        'phone_number',
        'phone_number_verified_at',
        'country',
        'need_change_password',
        'country_phone',
        'first_name',
        'last_name',
        'birthday',
        'allow_marketing_notifications',
        'allow_remainders_notifications'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'deleted_at',
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
            'need_change_password' => 'boolean',
            'birthday' => 'date:Y/m/d',
            'allow_remainders_notifications' => 'boolean',
            'allow_marketing_notifications' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasPermission('backoffice_access');
    }

    public function loginProviders(): HasMany
    {
        return $this->hasMany(LoginProvider::class);
    }

    public function wishlist(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    public function address(): HasOne
    {
        return $this->hasOne(CustomerAddress::class);
    }

    public function addressComplete(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!$this->address) {
                    return null;
                }
                return str($this->address?->address_line_1 . ', ' . ' ' . $this->address?->city)->limit(45);
            }
        );
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    public function avatarUrl(): Attribute
    {
        return Attribute::make(get: function () {
            if ($this->hasMedia('avatar')) {
                return $this->getFirstMediaUrl('avatar');
            } else {
                return \Cache::rememberForever('avatar_' . $this->id, function () {
                    return "https://gravatar.com/avatar/" . hash('sha256', strtolower(trim($this->email))) . '?d=mp&s=200';
                });
            }
        })->shouldCache();
    }

    public function language(): Attribute
    {
        return Attribute::make(get: function () {
            return match ($this->country) {
                'PT' => 'pt',
                'ES' => 'es',
                'FR' => 'fr',
                'DE' => 'de',
                default => 'en'
            };
        });
    }

    public function devices()
    {
        return $this->hasMany(Device::class);
    }

    public function routeNotificationForExpo(): Collection
    {
        return $this->devices->pluck('expo_token');
    }
}
