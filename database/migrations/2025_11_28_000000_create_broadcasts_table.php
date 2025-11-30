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
        Schema::create('broadcasts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('activity_date');
            $table->string('activity_location')->nullable();
            $table->integer('required_writers')->nullable();
            $table->integer('required_photographers')->nullable();
            $table->integer('total_required_members')->default(0);
            
            // Status of the broadcast
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
            
            // Who sent the broadcast
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            
            // When it was sent
            $table->timestamp('sent_at')->nullable();
            
            // Response tracking
            $table->integer('total_recipients')->default(0);
            $table->integer('accepted_count')->default(0);
            $table->integer('declined_count')->default(0);
            $table->integer('pending_count')->default(0);
            
            $table->timestamps();
        });

        // Create pivot table for broadcast recipients
        Schema::create('broadcast_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('broadcast_id')->constrained('broadcasts')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            
            // Response status of each recipient
            $table->enum('response_status', ['pending', 'accepted', 'declined'])->default('pending');
            $table->text('response_message')->nullable();
            $table->timestamp('responded_at')->nullable();
            
            // Availability info at time of broadcast
            $table->string('availability_type')->nullable(); // 'preferred', 'possible'
            $table->string('availability_times')->nullable(); // '09:00-17:00'
            
            $table->timestamps();
            
            // Ensure a user can only be a recipient once per broadcast
            $table->unique(['broadcast_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('broadcast_recipients');
        Schema::dropIfExists('broadcasts');
    }
};
