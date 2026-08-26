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
        Schema::create('stores', function (Blueprint $table) {

            $table->id();

            $table->foreignId('seller_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();


            $table->string('name');
            $table->string('slug')->unique();

            $table->string('logo')->nullable();
            $table->string('banner')->nullable();

            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();

            $table->string('short_description', 500)->nullable();
            $table->text('long_description')->nullable();

            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code', 20)->nullable();

            $table->enum('status', [
                'draft',
                'pending',
                'active',
                'suspended',
                'rejected',
            ])->default('draft')->index();

            $table->string('currency', 3)->default('MXN');
            $table->string('timezone')->default('America/Mexico_City');
            $table->string('country', 2)->default('MX');

            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();

            $table->boolean('is_featured')->default(false)->index();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('suspended_at')->nullable();

            $table->json('social_links')->nullable();
            $table->json('settings')->nullable();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
