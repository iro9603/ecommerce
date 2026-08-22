<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->foreignId('approved_by')
                ->nullable()
                ->after('approved_at')
                ->constrained('admins')
                ->nullOnDelete();
            $table->timestamp('rejected_at')->nullable()->after('suspended_at');
            $table->text('rejection_reason')->nullable()->after('rejected_at');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['rejected_at', 'rejection_reason']);
        });
    }
};
