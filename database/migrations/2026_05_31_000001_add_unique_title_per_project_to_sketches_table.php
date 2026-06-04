<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sketches', function (Blueprint $table) {
            $table->unique(['project_id', 'title']);
        });
    }

    public function down(): void
    {
        Schema::table('sketches', function (Blueprint $table) {
            $table->dropUnique(['sketches_project_id_title_unique']);
        });
    }
};
