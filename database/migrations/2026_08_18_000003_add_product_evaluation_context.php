<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_approval_reviews', function (Blueprint $table) {
            $table->json('evaluation_context')->nullable()->after('snapshot');
            $table->char('context_hash', 64)->nullable()->after('evaluation_context');
        });

        // The fingerprint algorithm is changing from a combined
        // content+eligibility snapshot to a content-only snapshot. Existing
        // approvals cannot prove what was reviewed under the new contract.
        DB::table('products')
            ->where('approved_status', 'approved')
            ->update([
                'approved_status' => 'pending',
                'reviewed_version' => null,
                'approved_at' => null,
                'approved_by' => null,
                'moderation_reason' => 'Security review required after separating product content from seller eligibility context.',
                'moderation_fingerprint' => null,
                'risk_level' => null,
                'risk_score' => null,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('product_approval_reviews', function (Blueprint $table) {
            $table->dropColumn(['evaluation_context', 'context_hash']);
        });
    }
};
