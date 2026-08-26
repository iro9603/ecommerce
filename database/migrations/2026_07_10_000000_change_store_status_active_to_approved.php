<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Change the store status enum from 'active' to 'approved' and migrate
     * any existing 'active' rows to 'approved'.
     */
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->enum('status', [
                'draft',
                'pending',
                'active',
                'approved',
                'suspended',
                'rejected',
            ])->default('draft')->change();
        });

        DB::table('stores')->where('status', 'active')->update(['status' => 'approved']);

        Schema::table('stores', function (Blueprint $table) {
            $table->enum('status', [
                'draft',
                'pending',
                'approved',
                'suspended',
                'rejected',
            ])->default('draft')->change();
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->enum('status', [
                'draft',
                'pending',
                'active',
                'approved',
                'suspended',
                'rejected',
            ])->default('draft')->change();
        });

        DB::table('stores')->where('status', 'approved')->update(['status' => 'active']);

        Schema::table('stores', function (Blueprint $table) {
            $table->enum('status', [
                'draft',
                'pending',
                'active',
                'suspended',
                'rejected',
            ])->default('draft')->change();
        });
    }
};
