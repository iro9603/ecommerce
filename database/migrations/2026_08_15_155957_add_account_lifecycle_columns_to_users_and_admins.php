<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addLifecycleColumns('users');
        $this->addLifecycleColumns('admins');
    }

    public function down(): void
    {
        $this->dropLifecycleColumns('admins');
        $this->dropLifecycleColumns('users');
    }

    private function addLifecycleColumns(string $tableName): void
    {
        if (! Schema::hasColumn($tableName, 'status')) {
            Schema::table($tableName, static function (Blueprint $table): void {
                $table->boolean('status')->nullable();
            });

            DB::table($tableName)
                ->select('id')
                ->whereNull('status')
                ->chunkById(500, static function ($rows) use ($tableName): void {
                    DB::table($tableName)
                        ->whereIn('id', $rows->pluck('id')->all())
                        ->update(['status' => false]);
                });

            Schema::table($tableName, static function (Blueprint $table): void {
                $table->boolean('status')->default(false)->nullable(false)->change();
            });
        }

        if (! Schema::hasColumn($tableName, 'deleted_at')) {
            Schema::table($tableName, static function (Blueprint $table): void {
                $table->softDeletesDatetime();
            });
        }
    }

    private function dropLifecycleColumns(string $tableName): void
    {
        if (Schema::hasColumn($tableName, 'deleted_at')) {
            Schema::table($tableName, static function (Blueprint $table): void {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasColumn($tableName, 'status')) {
            Schema::table($tableName, static function (Blueprint $table): void {
                $table->dropColumn('status');
            });
        }
    }
};
