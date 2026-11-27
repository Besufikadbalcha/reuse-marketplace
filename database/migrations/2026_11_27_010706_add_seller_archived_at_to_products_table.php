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
        Schema::table('products', function (Blueprint $table): void {
            $table->timestamp('seller_archived_at')->nullable();
            $table->index(['seller_id', 'seller_archived_at']);
            $table->index(['status', 'archived_at', 'seller_archived_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex(['seller_id', 'seller_archived_at']);
            $table->dropIndex(['status', 'archived_at', 'seller_archived_at']);
            $table->dropColumn('seller_archived_at');
        });
    }
};
