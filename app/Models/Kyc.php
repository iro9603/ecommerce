<?php

namespace App\Models;

use App\Services\SellerEligibilityService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Kyc extends Model
{
    protected $fillable = ['status'];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'document_expiry_date' => 'date',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'verified_at' => 'datetime',
            'eligibility_epoch' => 'integer',
            'expiration_reconciled_for' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }

    public function isEligibleAt(?CarbonInterface $at = null): bool
    {
        return app(SellerEligibilityService::class)->isKycEligible($this, $at);
    }

    public function scopeEligibleAt(
        Builder $query,
        ?CarbonInterface $at = null,
    ): Builder {
        return app(SellerEligibilityService::class)
            ->applyEligibleKycQuery($query, $at);
    }
}
