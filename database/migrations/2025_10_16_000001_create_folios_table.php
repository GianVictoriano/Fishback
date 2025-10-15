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
        Schema::create('folios', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('theme');
            $table->foreignId('lead_organizer_id')->constrained('users')->onDelete('cascade');
            $table->boolean('is_journalists_only')->default(true);
            $table->enum('status', ['draft', 'open', 'closed', 'published'])->default('draft');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('folios');
    }
};
