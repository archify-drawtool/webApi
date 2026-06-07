<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->replaceEdgeType('smoothstep', 'straight');
    }

    public function down(): void
    {
        $this->replaceEdgeType('straight', 'smoothstep');
    }

    private function replaceEdgeType(string $from, string $to): void
    {
        DB::table('sketches')
            ->whereNotNull('canvas_state')
            ->lazyById()
            ->each(function (object $row) use ($from, $to) {
                $state = json_decode($row->canvas_state, true);

                if (empty($state['edges'])) {
                    return;
                }

                $hasMatch = collect($state['edges'])->contains(
                    fn (array $edge) => ($edge['type'] ?? null) === $from,
                );

                if (! $hasMatch) {
                    return;
                }

                $state['edges'] = array_map(
                    fn (array $edge) => isset($edge['type']) && $edge['type'] === $from
                        ? array_merge($edge, ['type' => $to])
                        : $edge,
                    $state['edges'],
                );

                DB::table('sketches')
                    ->where('id', $row->id)
                    ->update(['canvas_state' => json_encode($state)]);
            });
    }
};
