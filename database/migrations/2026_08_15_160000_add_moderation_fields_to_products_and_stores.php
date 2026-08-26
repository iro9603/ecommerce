<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('moderation_version')->default(0)->after('approved_status');
            $table->unsignedBigInteger('reviewed_version')->nullable()->after('moderation_version');
            $table->timestamp('submitted_at')->nullable()->after('reviewed_version');
            $table->timestamp('approved_at')->nullable()->after('submitted_at');
            $table->foreignId('approved_by')
                ->nullable()
                ->after('approved_at')
                ->constrained('admins')
                ->nullOnDelete();
            $table->text('moderation_reason')->nullable()->after('approved_by');
            $table->string('risk_level', 20)->nullable()->after('moderation_reason');
            $table->unsignedTinyInteger('risk_score')->nullable()->after('risk_level');
            $table->char('moderation_fingerprint', 64)->nullable()->after('risk_score');

            $table->index(['approved_status', 'status'], 'products_publication_status_index');
            $table->index(['moderation_version', 'reviewed_version'], 'products_moderation_versions_index');
        });

        Schema::table('stores', function (Blueprint $table) {
            // A store must be explicitly trusted before a queued evaluation
            // may approve one of its products without a human reviewer.
            $table->boolean('auto_approve_products')->default(false)->after('is_featured')->index();
        });

        // Existing approvals predate versioned moderation and cannot be
        // trusted as having reviewed the current content. Require a fresh
        // review instead of silently grandfathering potentially unsafe HTML.
        DB::table('products')
            ->select('id')
            ->where('approved_status', 'approved')
            ->chunkById(500, static function ($products): void {
                DB::table('products')
                    ->whereIn('id', $products->pluck('id')->all())
                    ->update([
                        'approved_status' => 'pending',
                        'moderation_reason' => 'Security review required after enabling versioned moderation.',
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('auto_approve_products');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_publication_status_index');
            $table->dropIndex('products_moderation_versions_index');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn([
                'moderation_version',
                'reviewed_version',
                'submitted_at',
                'approved_at',
                'moderation_reason',
                'risk_level',
                'risk_score',
                'moderation_fingerprint',
            ]);
        });
    }
};
