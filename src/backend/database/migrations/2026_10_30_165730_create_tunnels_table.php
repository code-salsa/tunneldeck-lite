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
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 100)->unique();
            $table->integer('port')->unique()->index();
            $table->string('status')->default('stopped')->index(); // stopped, starting, running, error
            $table->string('public_url')->nullable();
            $table->integer('pid')->nullable();
            $table->string('process_uuid')->nullable();
            $table->timestamp('last_started_at')->nullable();
            $table->timestamp('last_stopped_at')->nullable();
            $table->string('custom_subdomain')->nullable();
            $table->unsignedBigInteger('custom_domain_id')->nullable();
            $table->boolean('is_root_domain')->default(false);
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
