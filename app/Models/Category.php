<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'is_active',
        'image',
        'icon',
        'position',
        'meta_title',
        'meta_description',
    ];

    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')
            ->orderBy('position')
            ->orderBy('name');
    }

    public static function getNested($parentId = null, $depth = 0, $maxDepth = 3)
    {
        if ($depth >= $maxDepth) {
            return [];
        }

        $categories = self::where('parent_id', $parentId)->orderBy('position')->get();
        foreach ($categories as $cat) {
            $cat->children_nested = self::getNested($cat->id, $depth + 1, $maxDepth);
        }

        return $categories;
    }

    function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }
}
