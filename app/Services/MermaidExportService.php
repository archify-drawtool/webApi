<?php

namespace App\Services;

class MermaidExportService
{
    /** Sanitize a VueFlow ID to a valid Mermaid identifier. */
    public function sanitizeId(string $id): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-]/', '_', $id);
    }

    /**
     * Escape characters in a Mermaid label using HTML entities.
     * Mermaid renders HTML entities correctly inside quoted labels.
     */
    public function escapeLabel(string $label): string
    {
        $label = str_replace('"', '&quot;', $label);
        $label = str_replace('<', '&lt;', $label);
        $label = str_replace('>', '&gt;', $label);

        return $label;
    }

    /** Supported Mermaid node shape keys, configurable via config/node_types.php. */
    public const SHAPES = [
        'rectangle',
        'subroutine',
        'cylinder',
        'hexagon',
        'circle',
        'rounded',
    ];

    /**
     * Convert a single VueFlow node to a Mermaid node declaration.
     *
     * @param  array{id: string, type?: string, data?: array{label?: string}}  $node
     * @param  string  $mermaidShape  One of the values in self::SHAPES
     *
     * @throws \InvalidArgumentException When the node has no id.
     */
    public function convertNode(array $node, string $mermaidShape = 'rectangle'): string
    {
        if (empty($node['id'])) {
            throw new \InvalidArgumentException('A node must have a non-empty id to be converted to Mermaid.');
        }

        $safeId = $this->sanitizeId($node['id']);
        $rawLabel = trim($node['data']['label'] ?? '');
        $label = $rawLabel !== '' ? $rawLabel : $node['id'];
        $escapedLabel = $this->escapeLabel($label);

        return match ($mermaidShape) {
            'subroutine' => "{$safeId}[[\"$escapedLabel\"]]",
            'cylinder' => "{$safeId}[(\"$escapedLabel\")]",
            'hexagon' => "{$safeId}{{\"$escapedLabel\"}}",
            'circle' => "{$safeId}((\"$escapedLabel\"))",
            'rounded' => "{$safeId}(\"$escapedLabel\")",
            default => "{$safeId}[\"$escapedLabel\"]",
        };
    }

    /** @return array<string, string> type → mermaid_shape lookup from config/node_types.php */
    public function buildShapeMap(): array
    {
        return collect(config('node_types'))
            ->mapWithKeys(fn (array $type) => [$type['type'] => $type['mermaid_shape'] ?? 'rectangle'])
            ->all();
    }

    /** Convert a node to Mermaid, resolving shape via config/node_types.php. */
    public function nodeToMermaid(array $node): string
    {
        $shapeMap = $this->buildShapeMap();

        return $this->convertNode($node, $shapeMap[$node['type'] ?? ''] ?? 'rectangle');
    }

    /** Convert a collection of VueFlow nodes to a Mermaid flowchart string. */
    public function exportNodes(array $nodes): string
    {
        $shapeMap = $this->buildShapeMap();

        $declarations = collect($nodes)
            ->filter(fn (array $node) => ! empty($node['id']))
            ->map(fn (array $node) => '  '.$this->convertNode($node, $shapeMap[$node['type'] ?? ''] ?? 'rectangle'))
            ->join("\n");

        return $declarations !== ''
            ? "flowchart TD\n{$declarations}"
            : 'flowchart TD';
    }
}
