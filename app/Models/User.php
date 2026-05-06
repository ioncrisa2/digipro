<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\SystemNavigation;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, HasRoles, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone_number',
        'whatsapp_number',
        'address',
        'company_name',
        'billing_address',
        'billing_recipient_name',
        'billing_province_id',
        'billing_regency_id',
        'billing_district_id',
        'billing_village_id',
        'billing_postal_code',
        'billing_address_detail',
        'billing_npwp',
        'billing_nik',
        'billing_email',
        'password',
        'avatar_url'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
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
            'two_factor_confirmed_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (self $user): void {
            if (! $user->wasChanged(['email', 'phone_number', 'billing_nik'])) {
                return;
            }

            $profile = $user->signatureProfile;
            if (! $profile) {
                return;
            }

            $profile->forceFill([
                'peruri_email' => $user->email,
                'peruri_phone' => $user->phone_number,
                'nik' => $user->billing_nik,
                'registration_status' => null,
                'kyc_status' => null,
                'specimen_status' => null,
                'certificate_status' => null,
                'keyla_status' => null,
                'keyla_qr_image' => null,
                'last_checked_at' => null,
                'last_error' => 'Profil customer berubah. Silakan lakukan onboarding PDS kembali.',
            ])->save();
        });
    }

    public function hasAdminAccess(): bool
    {
        $roles = array_values(array_filter([
            config('access-control.super_admin.enabled', true)
                ? config('access-control.super_admin.name', 'super_admin')
                : null,
            'admin',
        ]));

        return ! empty($roles) && $this->hasAnyRole($roles);
    }

    public function isSuperAdmin(): bool
    {
        if (! config('access-control.super_admin.enabled', true)) {
            return false;
        }

        return $this->hasRole((string) config('access-control.super_admin.name', 'super_admin'));
    }

    public function isReviewer(): bool
    {
        return $this->hasRole('Reviewer');
    }

    public function hasAdminNavigationAccess(): bool
    {
        return SystemNavigation::hasContextAccess($this, 'admin');
    }

    public function systemSectionPermissions(): array
    {
        return SystemNavigation::permissionsForUser($this);
    }

    public function cancellationRequests(): HasMany
    {
        return $this->hasMany(AppraisalRequestCancellation::class);
    }

    public function signatureProfile(): HasOne
    {
        return $this->hasOne(UserSignatureProfile::class);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
