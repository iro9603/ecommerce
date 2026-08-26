<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'approved_status')) {
            Schema::table('products', static function (Blueprint $table): void {
                $table->enum('approved_status', ['approved', 'pending', 'rejected'])
                    ->nullable()
                    ->after('status');
            });

            DB::table('products')
                ->select('id')
                ->chunkById(500, static function ($products): void {
                    DB::table('products')
                        ->whereIn('id', $products->pluck('id')->all())
                        ->update(['approved_status' => 'pending']);
                });

            Schema::table('products', static function (Blueprint $table): void {
                $table->enum('approved_status', ['approved', 'pending', 'rejected'])
                    ->default('pending')
                    ->nullable(false)
                    ->change();
            });
        }

        DB::table('products')
            ->select('id')
            ->where('status', 'pending')
            ->chunkById(500, static function ($products): void {
                DB::table('products')
                    ->whereIn('id', $products->pluck('id')->all())
                    ->update(['status' => 'active']);
            });

        Schema::table('products', static function (Blueprint $table): void {
            $table->enum('status', ['active', 'inactive', 'draft'])->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', static function (Blueprint $table): void {
            $table->enum('status', ['active', 'inactive', 'draft', 'pending'])->nullable()->change();
        });

        if (Schema::hasColumn('products', 'approved_status')) {
            Schema::table('products', static function (Blueprint $table): void {
                $table->dropColumn('approved_status');
            });
        }
    }
};
