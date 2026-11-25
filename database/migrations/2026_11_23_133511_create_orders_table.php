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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_id')->index()->constrained('users')->restrictOnDelete();
            $table->string('order_number')->unique();
            $table->enum('status', ['pending', 'confirmed', 'completed', 'cancelled'])->default('pending');
            $table->unsignedBigInteger('total_amount_minor');
            $table->enum('fulfillment_method', ['pickup', 'cash_on_delivery']);
            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();
            $table->text('delivery_address')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
