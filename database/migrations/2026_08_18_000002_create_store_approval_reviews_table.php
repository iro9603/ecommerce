<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_approval_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('version');
            $table->string('status', 20)->default('pending');
            $table->string('source', 20)->default('manual');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('submission_reason')->nullable();
            $table->text('decision_reason')->nullable();
            $table->json('snapshot');
            $table->json('eligibility_snapshot')->nullable();
            $table->char('content_hash', 64);
            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'created_at'], 'store_approval_reviews_store_created_index');
            $table->index(['store_id', 'version'], 'store_approval_reviews_store_version_index');
            $table->index(['status'], 'store_approval_reviews_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_approval_reviews');
    }
};
