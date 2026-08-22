<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->unsignedBigInteger('moderation_version')->default(0)->after('is_active');
            $table->unsignedBigInteger('reviewed_version')->nullable()->after('moderation_version');
            $table->timestamp('submitted_at')->nullable()->after('reviewed_version');
            $table->char('moderation_fingerprint', 64)->nullable()->after('submitted_at');
            $table->text('moderation_reason')->nullable()->after('moderation_fingerprint');

            $table->index(['status'], 'stores_status_moderation_index');
            $table->index(['moderation_version', 'reviewed_version'], 'stores_moderation_versions_index');
        });

        // Legacy approved stores have no review snapshot or history. They
        // cannot safely remain approved under versioned moderation, so they
        // are moved back to the review queue instead of being grandfathered.
        DB::table('stores')
            ->where('status', 'approved')
            ->update([
                'status' => 'pending',
                'is_active' => false,
                'approved_at' => null,
                'approved_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
                'suspended_at' => null,
                'moderation_version' => 0,
                'reviewed_version' => null,
                'moderation_fingerprint' => null,
                'moderation_reason' => 'Security review required after enabling store moderation versioning.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropIndex('stores_status_moderation_index');
            $table->dropIndex('stores_moderation_versions_index');
            $table->dropColumn([
                'moderation_version',
                'reviewed_version',
                'submitted_at',
                'moderation_fingerprint',
                'moderation_reason',
            ]);
        });
    }
};
