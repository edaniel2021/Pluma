<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * No organization_id here - tenant isolation is inherited transitively
     * through agent_threads, same as post_comments/post_errors through posts.
     */
    public function up(): void
    {
        Schema::create('agent_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_thread_id')->constrained()->cascadeOnDelete();
            // user|assistant|tool
            $table->string('role');
            $table->text('content')->nullable();
            // Set on tool-role messages (which tool produced this result) and
            // on assistant messages that only requested tool calls.
            $table->string('tool_name')->nullable();
            // Links a tool-role message back to the specific entry in the
            // preceding assistant message's tool_calls array.
            $table->string('tool_call_id')->nullable();
            // Assistant messages that request tool calls store the raw
            // [{id, name, arguments}] array here instead of/alongside content.
            $table->json('tool_calls')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_messages');
    }
};
