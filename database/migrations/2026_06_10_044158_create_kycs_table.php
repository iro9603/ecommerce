<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('kycs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('admins')
                ->nullOnDelete();

            $table->enum('status', [
                'pending',
                'under_review',
                'approved',
                'rejected',
            ])->default('pending')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->text('review_notes')->nullable();

            $table->string('full_name');
            $table->date('date_of_birth');
            $table->enum('gender', [
                'male',
                'female',
                'other',
                'prefer_not_to_say',
            ])->nullable();
            $table->string('nationality', 2);

            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->string('city');
            $table->string('state')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 2);

            $table->enum('document_type', [
                'passport',
                'driving_license',
                'id_card',
            ])->index();
            $table->string('document_number');
            $table->string('document_country', 2);
            $table->date('document_expiry_date')->nullable();
            $table->string('document_front_path');
            $table->string('document_back_path')->nullable();
            $table->string('selfie_path')->nullable();
            $table->string('proof_of_address_path')->nullable();

            $table->string('verification_provider')->nullable();
            $table->string('provider_reference')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kycs');
    }
};
