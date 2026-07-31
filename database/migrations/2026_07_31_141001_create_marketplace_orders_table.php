<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Low-priority schema stub for Postiz's Marketplace feature (an
     * organization hiring another to manage/create content) - deliberately
     * schema-only, no UI/actions, per the plan's reduced scope for this
     * item. No BelongsToOrganization: an order references two distinct
     * organizations (buyer and seller), which a single-tenant scope can't
     * cleanly express.
     */
    public function up(): void
    {
        Schema::create('marketplace_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('seller_organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('usd');
            // pending|accepted|in_progress|delivered|completed|cancelled
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_orders');
    }
};
