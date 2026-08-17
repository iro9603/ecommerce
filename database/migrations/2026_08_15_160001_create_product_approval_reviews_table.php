<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_approval_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedBigInteger('version');
            $table->string('status', 20)->default('pending');
            $table->string('source', 20)->default('automatic');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->unsignedTinyInteger('risk_score')->nullable();
            $table->string('risk_level', 20)->nullable();
            $table->json('risk_reasons')->nullable();
            $table->text('submission_reason')->nullable();
            $table->text('decision_reason')->nullable();
            $table->char('content_hash', 64);
            $table->json('snapshot');
            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'version']);
            $table->index(['status', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_approval_reviews');
    }
};
