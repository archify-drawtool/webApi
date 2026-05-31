<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('sketches')
            ->whereNull('created_by')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('photos')
                    ->whereColumn('photos.sketch_id', 'sketches.id')
                    ->whereNotNull('photos.sketch_id');
            })
            ->update([
                'created_by' => DB::raw('(select projects.created_by from projects where projects.id = sketches.project_id)'),
            ]);
    }

    public function down(): void
    {
        //
    }
};
