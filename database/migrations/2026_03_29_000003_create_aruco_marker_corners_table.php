<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aruco_marker_corners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aruco_marker_id')
                ->constrained('aruco_markers')
                ->cascadeOnDelete();
            $table->enum('position', ['TL', 'TR', 'BR', 'BL']);
            $table->float('x');
            $table->float('y');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aruco_marker_corners');
    }
};
