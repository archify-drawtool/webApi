<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photo_previews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('status')->default('detecting');
            $table->unsignedInteger('nodes_count')->nullable();
            $table->unsignedInteger('edges_count')->nullable();
            $table->foreignId('detection_result_id')->nullable()->constrained('detection_results')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photo_previews');
    }
};
