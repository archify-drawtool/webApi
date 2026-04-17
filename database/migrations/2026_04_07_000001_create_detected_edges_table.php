<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detected_edges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('detection_result_id')->constrained('detection_results')->cascadeOnDelete();
            $table->foreignId('edge_marker_id')->constrained('aruco_markers')->cascadeOnDelete();
            $table->foreignId('source_marker_id')->constrained('aruco_markers')->cascadeOnDelete();
            $table->foreignId('target_marker_id')->constrained('aruco_markers')->cascadeOnDelete();
            $table->string('edge_type');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detected_edges');
    }
};
