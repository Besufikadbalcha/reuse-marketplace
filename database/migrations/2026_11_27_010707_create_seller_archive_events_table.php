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
        Schema::create('seller_archive_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seller_id')->constrained('users');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->text('reason')->nullable();
            $table->unsignedInteger('listings_affected')->default(0);
            $table->unsignedInteger('listings_restored')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['seller_id', 'created_at']);
            $table->index(['actor_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seller_archive_events');
    }
};
