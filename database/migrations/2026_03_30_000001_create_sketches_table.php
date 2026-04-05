<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sketches', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('project_id')->nullable()->constrained('projects')->cascadeOnDelete(); // In the future it could be possible for sketches to not be associated with a project, so we make this nullable
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('canvas_state')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sketches');
    }
};
