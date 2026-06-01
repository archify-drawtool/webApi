<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detected_edges', function (Blueprint $table) {
            $table->unsignedTinyInteger('retry_attempts')->default(0)->after('edge_type');
        });
    }

    public function down(): void
    {
        Schema::table('detected_edges', function (Blueprint $table) {
            $table->dropColumn('retry_attempts');
        });
    }
};
