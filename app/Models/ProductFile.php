<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductFile extends Model
{
    protected $fillable = [
        'product_id',
        'filename',
        'path',
        'extension',
        'size',
        'sha256',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }
}
