<?php

namespace App\Services;

class MermaidExportService
{
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

    // ─── Shared helpers ───────────────────────────────────────────────────────

    /**
     * Sanitize a VueFlow node or edge ID to a valid Mermaid identifier.
     * Replaces any character that is not alphanumeric, a dash, or an underscore with '_'.
     */
    public function sanitizeId(string $id): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-]/', '_', $id);
    }

    /**
     * Escape characters in a Mermaid label that would break the syntax.
     * Backslashes are escaped first, then double quotes.
     *
     * Shared by both node labels and edge labels.
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

    // ─── Node conversion ──────────────────────────────────────────────────────

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
     * @param  string  $mermaidShape  One of the values in self::SHAPES
     *
     * @throws \InvalidArgumentException When the node has no id.
     *
     * @example
     * $service->convertNode(['id' => 'abc-1', 'data' => ['label' => 'Mijn DB']], 'cylinder');
     * // → 'abc-1[("Mijn DB")]'
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
            'cylinder'   => "{$safeId}[(\"$escapedLabel\")]",
            'hexagon'    => "{$safeId}{{\"$escapedLabel\"}}",
            'circle'     => "{$safeId}((\"$escapedLabel\"))",
            'rounded'    => "{$safeId}(\"$escapedLabel\")",
            default      => "{$safeId}[\"$escapedLabel\"]",
        };
    }

    /**
     * Build a type → mermaid_shape lookup map from config/node_types.php.
     * Intended to be called once and reused when converting multiple nodes.
     *
     * @return array<string, string>  e.g. ['database' => 'cylinder', 'user' => 'circle', ...]
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
     * Convert a collection of VueFlow nodes to node declaration lines.
     *
     * - Builds the type → shape map once for the entire batch.
     * - Silently skips malformed nodes that have no id.
     *
     * @param  array<int, array{id?: string, type?: string, data?: array{label?: string}}>  $nodes
     * @return string[]
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

    // ─── Edge conversion ──────────────────────────────────────────────────────

    /**
     * Detect the arrow direction of a VueFlow edge based on its marker configuration.
     *
     * VueFlow stores arrow markers as markerStart / markerEnd on the edge object.
     * The detected direction maps to a key in config/mermaid.php → arrows.
     *
     * Detection rules:
     *   markerStart present AND markerEnd present → 'bi'
     *   only markerEnd present                   → 'mono'
     *   neither present                          → 'none'
     *
     * @param  array  $edge  Raw VueFlow edge from canvas_state
     * @return string        One of: 'none', 'mono', 'bi'
     */
    public function detectArrowType(array $edge): string
    {
        $hasStart = ! empty($edge['markerStart']);
        $hasEnd   = ! empty($edge['markerEnd']);

        if ($hasStart && $hasEnd) return 'bi';
        if ($hasEnd)              return 'mono';

        return 'none';
    }

    /**
     * Resolve the Mermaid arrow syntax string for a given arrow type.
     * Falls back to config/mermaid.php → default_arrow when the type is unknown.
     *
     * @param  string  $arrowType  One of: 'none', 'mono', 'bi'
     * @return string              e.g. '---', '-->', '<-->'
     */
    public function resolveArrow(string $arrowType): string
    {
        $arrows = config('mermaid.arrows', []);

        return $arrows[$arrowType] ?? config('mermaid.default_arrow', '-->');
    }

    /**
     * Convert a single VueFlow edge to a valid Mermaid flowchart edge declaration.
     *
     * - source and target IDs are sanitized.
     * - Arrow direction is determined by the presence of markerStart / markerEnd
     *   and resolved via config/mermaid.php → arrows.
     * - When a label is present it is escaped and placed between pipes: -->|"label"|
     * - When no label is present the arrow is used directly: -->
     *
     * @param  array{source: string, target: string, markerStart?: mixed, markerEnd?: mixed, label?: string}  $edge
     *
     * @throws \InvalidArgumentException When source or target is missing.
     *
     * @example
     * // Without label
     * $service->convertEdge(['source' => 'a', 'target' => 'b', 'markerEnd' => ['type' => 'arrowclosed']]);
     * // → 'a --> b'
     *
     * @example
     * // With label
     * $service->convertEdge(['source' => 'a', 'target' => 'b', 'markerEnd' => [...], 'label' => 'API call']);
     * // → 'a -->|"API call"| b'
     */
    public function convertEdge(array $edge): string
    {
        if (empty($edge['source']) || empty($edge['target'])) {
            throw new \InvalidArgumentException('An edge must have a non-empty source and target to be converted to Mermaid.');
        }

        $source = $this->sanitizeId($edge['source']);
        $target = $this->sanitizeId($edge['target']);
        $arrow  = $this->resolveArrow($this->detectArrowType($edge));

        $rawLabel = trim($edge['label'] ?? '');

        if ($rawLabel !== '') {
            $escapedLabel = $this->escapeLabel($rawLabel);

            return "{$source} {$arrow}|\"{$escapedLabel}\"| {$target}";
        }

        return "{$source} {$arrow} {$target}";
    }

    // ─── Full sketch export ───────────────────────────────────────────────────

    /**
     * Convert a full VueFlow canvas state (nodes + edges) to a complete
     * Mermaid flowchart string.
     *
     * - Node shape map and arrow config are each loaded once for the batch.
     * - Malformed nodes (no id) and malformed edges (no source or target)
     *   are silently skipped.
     * - Returns 'flowchart TD' without trailing newline when the canvas is empty.
     *
     * @param  array{nodes?: array, edges?: array}  $canvasState
     */
    public function exportSketch(array $canvasState): string
    {
        $nodes = $canvasState['nodes'] ?? [];
        $edges = $canvasState['edges'] ?? [];

        $shapeMap = $this->buildShapeMap();

        $nodeLines = collect($nodes)
            ->filter(fn (array $node) => ! empty($node['id']))
            ->map(fn (array $node) => '  '.$this->convertNode($node, $shapeMap[$node['type'] ?? ''] ?? 'rectangle'));

        $edgeLines = collect($edges)
            ->filter(fn (array $edge) => ! empty($edge['source']) && ! empty($edge['target']))
            ->map(fn (array $edge) => '  '.$this->convertEdge($edge));

        $body = $nodeLines->merge($edgeLines)->join("\n");

        return $body !== '' ? "flowchart TD\n{$body}" : 'flowchart TD';
    }
}
