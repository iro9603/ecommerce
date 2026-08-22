<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_files', function (Blueprint $table) {
            $table->char('sha256', 64)->nullable()->after('size');
        });
    }

    public function down(): void
    {
        Schema::table('product_files', function (Blueprint $table) {
            $table->dropColumn('sha256');
        });
    }
};
