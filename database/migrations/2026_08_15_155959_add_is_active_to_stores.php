<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('stores', 'is_active')) {
            Schema::table('stores', static function (Blueprint $table): void {
                $table->boolean('is_active')->nullable()->default(false)->after('status');
            });

            DB::table('stores')
                ->select('id')
                ->chunkById(500, static function ($stores): void {
                    DB::table('stores')
                        ->whereIn('id', $stores->pluck('id')->all())
                        ->update(['is_active' => false]);
                });

            Schema::table('stores', static function (Blueprint $table): void {
                $table->boolean('is_active')->default(false)->nullable(false)->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('stores', 'is_active')) {
            Schema::table('stores', static function (Blueprint $table): void {
                $table->dropColumn('is_active');
            });
        }
    }
};
