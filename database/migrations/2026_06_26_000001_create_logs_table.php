<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logs', function (Blueprint $table) {
            $table->id();
            $table->string('level', 32)->default('info');
            $table->string('channel', 64)->nullable();
            $table->string('event', 128)->nullable();
            $table->string('message');
            $table->json('context')->nullable();
            $table->json('meta')->nullable();
            $table->string('source', 128)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamps();

            $table->index(['level', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logs');
    }
};
