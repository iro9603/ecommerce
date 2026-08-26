<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Services\SellerEligibilityService;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'user_type',
    ];

    /**
     * The attributes that should be hidden for serialization
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
            'eligibility_epoch' => 'integer',
        ];
    }

    public function kyc(): HasOne
    {
        return $this->hasOne(Kyc::class);
    }

    public function store(): HasOne
    {
        return $this->hasOne(Store::class, 'seller_id');
    }

    public function isEligibleVendor(): bool
    {
        return (bool) app(SellerEligibilityService::class)
            ->sellerSnapshot($this)['eligible'];
    }

    /**
     * The route name that this user lands on after auth-related redirects
     * (e.g. login, registration or email verification). Vendors go to the
     * vendor dashboard; everyone else uses the customer dashboard.
     */
    public function homeRoute(): string
    {
        return $this->user_type === 'vendor' ? 'vendor.dashboard' : 'dashboard';
    }
}
