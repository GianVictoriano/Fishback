<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_type');
            $table->unsignedBigInteger('size');
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('mediable_id');
            $table->string('mediable_type');
            $table->timestamps();
            
            // Index for the polymorphic relationship
            $table->index(['mediable_id', 'mediable_type']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('media');
    }
};
