<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_eligibility_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('kyc_id')->nullable();
            $table->string('trigger', 80);
            $table->unsignedBigInteger('user_epoch')->nullable();
            $table->unsignedBigInteger('kyc_epoch')->nullable();
            $table->string('event_key', 191)->unique();
            $table->boolean('eligible');
            $table->json('snapshot');
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['user_id', 'occurred_at']);
            $table->index(['trigger', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_eligibility_events');
    }
};
