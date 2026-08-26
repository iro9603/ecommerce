<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedBigInteger('eligibility_epoch')->default(0);
        });

        Schema::table('kycs', function (Blueprint $table): void {
            $table->unsignedBigInteger('eligibility_epoch')->default(0);
            $table->date('expiration_reconciled_for')->nullable();
            $table->index(
                ['status', 'document_expiry_date', 'expiration_reconciled_for'],
                'kycs_expiration_reconciliation_index',
            );
        });

        Schema::table('stores', function (Blueprint $table): void {
            $table->unsignedBigInteger('eligibility_epoch')->default(0);
            $table->unsignedBigInteger('auto_approval_user_epoch')->nullable();
            $table->unsignedBigInteger('auto_approval_kyc_id')->nullable();
            $table->unsignedBigInteger('auto_approval_kyc_epoch')->nullable();
            $table->unsignedBigInteger('auto_approval_store_epoch')->nullable();
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedBigInteger('reviewed_user_eligibility_epoch')->nullable();
            $table->unsignedBigInteger('reviewed_kyc_id')->nullable();
            $table->unsignedBigInteger('reviewed_kyc_eligibility_epoch')->nullable();
            $table->unsignedBigInteger('reviewed_store_eligibility_epoch')->nullable();
            $table->char('reviewed_context_hash', 64)->nullable();
            $table->string('reviewed_policy_version', 64)->nullable();
        });

        DB::table('stores')
            ->select('id')
            ->where('auto_approve_products', true)
            ->chunkById(500, static function ($stores): void {
                DB::table('stores')->whereIn('id', $stores->pluck('id')->all())->update([
                    'auto_approve_products' => false,
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn([
                'reviewed_user_eligibility_epoch',
                'reviewed_kyc_id',
                'reviewed_kyc_eligibility_epoch',
                'reviewed_store_eligibility_epoch',
                'reviewed_context_hash',
                'reviewed_policy_version',
            ]);
        });

        Schema::table('stores', function (Blueprint $table): void {
            $table->dropColumn([
                'eligibility_epoch',
                'auto_approval_user_epoch',
                'auto_approval_kyc_id',
                'auto_approval_kyc_epoch',
                'auto_approval_store_epoch',
            ]);
        });

        Schema::table('kycs', function (Blueprint $table): void {
            $table->dropIndex('kycs_expiration_reconciliation_index');
            $table->dropColumn(['eligibility_epoch', 'expiration_reconciled_for']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('eligibility_epoch');
        });
    }
};
