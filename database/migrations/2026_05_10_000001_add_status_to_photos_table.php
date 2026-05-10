<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->string('status')->default('processing')->after('path');
            $table->text('error_message')->nullable()->after('status');
            $table->foreignId('sketch_id')->nullable()->after('error_message')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->dropForeign(['sketch_id']);
            $table->dropColumn(['status', 'error_message', 'sketch_id']);
        });
    }
};
