<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    protected $fillable = ['order'];

    public function controlledUrl(): string
    {
        return route('product-media.show', $this);
    }
}
