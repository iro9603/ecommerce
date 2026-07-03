<?php

namespace App\Models;

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
}
