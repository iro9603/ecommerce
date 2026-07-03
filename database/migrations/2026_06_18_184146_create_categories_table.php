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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('categories')
                ->nullOnDelete();

            $table->string('name')->index();
            $table->string('slug')->unique();

            $table->string('image')->nullable();
            $table->string('icon')->nullable();

            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);

            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['parent_id', 'is_active', 'position']);
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
