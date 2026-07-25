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
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            // Owner FK: stays `user_id` because Jetstream's base
            // Team::owner() relation hardcodes that column name.
            $table->foreignId('user_id')->index();
            $table->string('name');
            // Stays `personal_team`: HasTeams::personalTeam() and
            // Team::purge()/removeUser() hardcode this literal name.
            $table->boolean('personal_team');
            $table->string('subscription_tier')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
