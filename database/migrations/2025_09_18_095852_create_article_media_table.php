<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::create('article_media', function (Blueprint $table) {
        $table->id();
        $table->foreignId('article_id')->constrained()->onDelete('cascade');
        $table->string('file_path');
        $table->string('file_name');
        $table->string('file_type');
        $table->string('mime_type');
        $table->unsignedBigInteger('size');
        $table->json('metadata')->nullable();
        $table->timestamps();
    });
}

public function down()
{
    Schema::dropIfExists('article_media');
}
};
