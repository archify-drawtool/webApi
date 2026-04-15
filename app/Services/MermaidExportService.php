<?php

namespace App\Services;

class MermaidExportService
{
    /**
     * Sanitize a VueFlow node ID to a valid Mermaid identifier.
     * Replaces any character that is not alphanumeric, a dash, or an underscore with '_'.
     */
    public function sanitizeId(string $id): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-]/', '_', $id);
    }

    /**
     * Escape characters in a Mermaid node label that would break the syntax.
     * Backslashes are escaped first, then double quotes.
     *
     * Note: '$' is intentionally not escaped — it has no special meaning in
     * Mermaid flowchart label syntax, and escaping it (\$) would cause the
     * backslash to appear literally in the rendered output.
     */
    public function escapeLabel(string $label): string
    {
        $label = str_replace('\\', '\\\\', $label);
        $label = str_replace('"', '\\"', $label);

        return $label;
    }

    /**
     * Convert a single VueFlow node to a valid Mermaid flowchart node declaration.
     *
     * - The node ID is sanitized and used as the Mermaid identifier.
     * - The node label (data.label) is used as the display name.
     * - Falls back to the original node ID as label when no label is set.
     * - Special characters in the label are escaped so the output remains compilable.
     * - The mermaidShape parameter controls the Mermaid node shape syntax.
     *
     * @param  array{id: string, type?: string, data?: array{label?: string}}  $node
     * @param  string  $mermaidShape  One of: 'rectangle', 'cylinder', 'rounded'
     *
     * @throws \InvalidArgumentException When the node has no id.
     *
     * @example
     * $service->convertNode(['id' => 'abc-1', 'data' => ['label' => 'Mijn DB']], 'cylinder');
     * // → 'abc-1[("Mijn DB")]'
     */
    /**
     * All supported Mermaid node shapes and their syntax.
     *
     * Configurable via the 'mermaid_shape' field in config/node_types.php.
     * Add new entries here to support additional Mermaid shapes in the future.
     *
     * Shape reference:
     *   rectangle  → id["Label"]       standard box
     *   subroutine → id[["Label"]]     double-bordered box (server)
     *   cylinder   → id[("Label")]     database cylinder
     *   hexagon    → id{{"Label"}}     hexagon (application)
     *   circle     → id(("Label"))     circle (user/actor)
     *   rounded    → id("Label")       rounded rectangle
     */
    public const SHAPES = [
        'rectangle',
        'subroutine',
        'cylinder',
        'hexagon',
        'circle',
        'rounded',
    ];

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

    /**
     * Build a type → mermaid_shape lookup map from config/node_types.php.
     * Intended to be called once and reused when converting multiple nodes.
     *
     * @return array<string, string> e.g. ['database' => 'cylinder', 'user' => 'rounded', ...]
     */
    public function buildShapeMap(): array
    {
        return collect(config('node_types'))
            ->mapWithKeys(fn (array $type) => [$type['type'] => $type['mermaid_shape'] ?? 'rectangle'])
            ->all();
    }

    /**
     * Convert a single VueFlow node to a Mermaid declaration, resolving the
     * shape from the node's type via config/node_types.php.
     *
     * Falls back to 'rectangle' when the node type is unknown.
     *
     * @param  array{id: string, type?: string, data?: array{label?: string}}  $node
     */
    public function nodeToMermaid(array $node): string
    {
        $shapeMap = $this->buildShapeMap();

        return $this->convertNode($node, $shapeMap[$node['type'] ?? ''] ?? 'rectangle');
    }

    /**
     * Convert a collection of VueFlow nodes to a complete Mermaid flowchart string.
     *
     * - Builds the type → shape map once for the entire batch.
     * - Silently skips malformed nodes that have no id.
     * - Returns 'flowchart TD' (no trailing newline) when there are no valid nodes.
     *
     * @param  array<int, array{id?: string, type?: string, data?: array{label?: string}}>  $nodes
     */
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
