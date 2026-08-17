<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicateSlugs = DB::table('products')
            ->select('slug')
            ->groupBy('slug')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('slug');

        foreach ($duplicateSlugs as $slug) {
            $duplicateIds = DB::table('products')
                ->where('slug', $slug)
                ->orderBy('id')
                ->pluck('id')
                ->slice(1);

            foreach ($duplicateIds as $id) {
                $suffix = '-'.$id;
                $base = mb_substr((string) $slug, 0, 255 - mb_strlen($suffix));
                $candidate = $base.$suffix;
                $attempt = 1;

                while (DB::table('products')->where('slug', $candidate)->exists()) {
                    $attemptSuffix = $suffix.'-'.$attempt++;
                    $candidate = mb_substr(
                        (string) $slug,
                        0,
                        255 - mb_strlen($attemptSuffix)
                    ).$attemptSuffix;
                }

                DB::table('products')->where('id', $id)->update(['slug' => $candidate]);
            }
        }

        Schema::table('products', function (Blueprint $table) {
            $table->unique('slug', 'products_slug_unique');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_slug_unique');
        });
    }
};
