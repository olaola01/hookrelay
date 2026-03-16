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
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('source')->index();
            $table->string('event_id')->nullable()->index();
            $table->string('signature')->nullable();
            $table->json('headers');
            $table->longText('payload');
            $table->string('status')->default('received')->index();
            $table->timestamp('received_at')->nullable()->index();
            $table->timestamps();

            $table->index(['source', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
