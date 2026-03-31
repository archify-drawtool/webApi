<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aruco_markers', function (Blueprint $table) {
            $table->text('ocr_text')->nullable()->after('rotation');
        });
    }

    public function down(): void
    {
        Schema::table('aruco_markers', function (Blueprint $table) {
            $table->dropColumn('ocr_text');
        });
    }
};
