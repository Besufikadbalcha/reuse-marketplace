<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
        create table "promotion_payment_submissions" (
            "id" integer primary key autoincrement not null,
            "promotion_id" integer not null,
            "attempt" integer not null default '1',
            "method" varchar not null,
            "transaction_reference" varchar,
            "transaction_details" text not null,
            "proof_path" varchar not null,
            "proof_checksum" varchar not null,
            "status" varchar not null default 'pending',
            "pending_promotion_id" integer,
            "approved_promotion_id" integer,
            "rejection_reason" text,
            "reviewed_by" integer,
            "reviewed_at" datetime,
            "submitted_at" datetime not null,
            "created_at" datetime,
            "updated_at" datetime,
            constraint "promotion_payment_submissions_attempt_unique" unique ("promotion_id", "attempt"),
            constraint "promotion_payment_submissions_pending_promotion_unique" unique ("pending_promotion_id"),
            constraint "promotion_payment_submissions_approved_promotion_unique" unique ("approved_promotion_id"),
            foreign key("promotion_id") references "product_promotions"("id") on delete restrict,
            foreign key("pending_promotion_id") references "product_promotions"("id") on delete restrict,
            foreign key("approved_promotion_id") references "product_promotions"("id") on delete restrict,
            foreign key("reviewed_by") references "users"("id") on delete restrict,
            constraint "promotion_payment_submissions_method_valid" check ("method" in ('telebirr', 'bank_transfer')),
            constraint "promotion_payment_submissions_status_valid" check ("status" in ('pending', 'approved', 'rejected', 'superseded')),
            constraint "promotion_payment_submissions_attempt_positive" check ("attempt" > 0),
            constraint "promotion_payment_submissions_proof_checksum_valid" check (length("proof_checksum") = 64),
            constraint "promotion_payment_submissions_rejection_reason_valid" check ((("status" = 'rejected' and "rejection_reason" is not null and length(trim("rejection_reason")) > 0) or ("status" != 'rejected' and "rejection_reason" is null))),
            constraint "promotion_payment_submissions_review_valid" check ((("status" in ('approved', 'rejected') and "reviewed_by" is not null and "reviewed_at" is not null) or ("status" in ('pending', 'superseded') and "reviewed_by" is null and "reviewed_at" is null))),
            constraint "promotion_payment_submissions_state_valid" check ((("status" = 'pending' and "pending_promotion_id" = "promotion_id" and "approved_promotion_id" is null) or ("status" = 'approved' and "approved_promotion_id" = "promotion_id" and "pending_promotion_id" is null) or ("status" in ('rejected', 'superseded') and "pending_promotion_id" is null and "approved_promotion_id" is null)))
        )
        SQL);

        Schema::table('promotion_payment_submissions', function (Blueprint $table): void {
            $table->index(['status', 'reviewed_at']);
            $table->index('proof_checksum');
            $table->index('submitted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotion_payment_submissions');
    }
};
