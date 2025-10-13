<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('contributions', function (Blueprint $table) {
            $table->string('file_path')->nullable()->after('content'); // for artworks
            $table->string('content_file_path')->nullable()->after('file_path'); // for literature text file
        });
    }

    public function down()
    {
        Schema::table('contributions', function (Blueprint $table) {
            $table->dropColumn(['file_path', 'content_file_path']);
        });
    }
};
