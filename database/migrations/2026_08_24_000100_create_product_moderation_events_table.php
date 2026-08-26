<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_moderation_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('product_approval_review_id')
                ->nullable()
                ->constrained('product_approval_reviews')
                ->nullOnDelete();
            $table->unsignedBigInteger('version');
            $table->string('event_type', 40);
            $table->string('source', 20)->nullable();
            $table->string('actor_type', 100)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->char('content_hash', 64)->nullable();
            $table->char('context_hash', 64)->nullable();
            $table->json('snapshot')->nullable();
            $table->json('evaluation_context')->nullable();
            $table->json('risk_result')->nullable();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->char('event_key', 64)->unique();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['product_id', 'version', 'id'], 'product_moderation_event_timeline');
            $table->index(['event_type', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_moderation_events');
    }
};
