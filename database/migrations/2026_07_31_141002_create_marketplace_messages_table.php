<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Low-priority schema stub - see create_marketplace_orders_table. No own
     * organization_id: inherits transitively through marketplace_order,
     * same pattern as post_comments/post_errors through posts.
     */
    public function up(): void
    {
        Schema::create('marketplace_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->text('content');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_messages');
    }
};
