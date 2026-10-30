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
        Schema::create('tunnels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('subdomain')->unique();
            $table->string('protocol')->default('http');
            $table->integer('port')->default(3000);
            $table->string('target_host')->default('127.0.0.1');
            $table->string('public_url');
            $table->enum('status', ['active', 'paused', 'stopped'])->default('active');
            $table->unsignedBigInteger('connections_count')->default(0);
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tunnels');
    }
};
