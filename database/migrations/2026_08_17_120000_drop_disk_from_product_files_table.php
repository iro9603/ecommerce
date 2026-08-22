<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The disk column was never controlled by the write path: every digital
     * product file is stored on the configured upload disk
     * (config('products.digital_upload.disk')). Using a database column as the
     * deletion authority allowed a tampered or legacy value to redirect deletes
     * to an unrelated disk, orphaning the real file. The column is removed so
     * deletion and storage always share a single configured disk.
     */
    public function up(): void
    {
        if (! Schema::hasTable('product_files')) {
            return;
        }

        Schema::table('product_files', function (Blueprint $table) {
            if (Schema::hasColumn('product_files', 'disk')) {
                $table->dropColumn('disk');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('product_files')) {
            return;
        }

        Schema::table('product_files', function (Blueprint $table) {
            if (! Schema::hasColumn('product_files', 'disk')) {
                $table->enum('disk', ['local', 'public', 's3'])->default('local');
            }
        });
    }
};
