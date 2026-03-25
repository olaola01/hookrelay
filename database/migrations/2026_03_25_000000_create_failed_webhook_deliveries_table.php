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
        Schema::create('failed_webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_event_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('final_attempts');
            $table->text('last_error');
            $table->timestamp('failed_at')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('failed_webhook_deliveries');
    }
};
