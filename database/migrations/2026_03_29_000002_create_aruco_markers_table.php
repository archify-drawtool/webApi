<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aruco_markers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('detection_result_id')
                ->constrained('detection_results')
                ->cascadeOnDelete();
            $table->unsignedInteger('marker_id');
            $table->float('center_x');
            $table->float('center_y');
            $table->float('rotation');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aruco_markers');
    }
};
