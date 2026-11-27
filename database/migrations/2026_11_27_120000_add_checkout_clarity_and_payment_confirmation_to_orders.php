<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->string('fulfillment_status', 40)->nullable()->after('seller_status');
            $table->timestamp('seller_cash_received_at')->nullable()->after('fulfillment_status');
            $table->foreignId('seller_cash_received_by')->nullable()->after('seller_cash_received_at')->constrained('users')->nullOnDelete();
            $table->timestamp('buyer_confirmed_received_at')->nullable()->after('seller_cash_received_by');
            $table->timestamp('buyer_disputed_at')->nullable()->after('buyer_confirmed_received_at');
            $table->string('buyer_dispute_reason', 1000)->nullable()->after('buyer_disputed_at');
            $table->timestamp('dispute_resolved_at')->nullable()->after('buyer_dispute_reason');
            $table->foreignId('dispute_resolved_by')->nullable()->after('dispute_resolved_at')->constrained('users')->nullOnDelete();
            $table->string('dispute_resolution_note', 1000)->nullable()->after('dispute_resolved_by');
        });

        DB::table('order_items')->where('seller_status', 'completed')->update(['fulfillment_status' => 'completed']);
        DB::table('order_items')->where('seller_status', 'confirmed')->whereNull('fulfillment_status')->update(['fulfillment_status' => 'confirmed']);
        DB::table('order_items')->whereNull('fulfillment_status')->update(['fulfillment_status' => 'pending_confirmation']);

        Schema::create('order_item_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_role', 20);
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->string('note', 1000)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['order_item_id', 'created_at']);
            $table->index(['order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_status_histories');

        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('dispute_resolved_by');
            $table->dropConstrainedForeignId('seller_cash_received_by');
            $table->dropColumn([
                'fulfillment_status',
                'seller_cash_received_at',
                'buyer_confirmed_received_at',
                'buyer_disputed_at',
                'buyer_dispute_reason',
                'dispute_resolved_at',
                'dispute_resolution_note',
            ]);
        });
    }
};
