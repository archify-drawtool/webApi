<?php

namespace App\Services;

class DrawioExportService
{
    public function sanitizeId(string $id): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-]/', '_', $id);
    }

    public function nodeId(string $id): string
    {
        return 'n_'.$this->sanitizeId($id);
    }

    public function edgeId(string $id): string
    {
        return 'e_'.$this->sanitizeId($id);
    }

    public function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    public function escapeLabel(string $label): string
    {
        return str_replace(["\r\n", "\r", "\n"], '&#10;', $this->escapeXml($label));
    }

    public function buildShapeMap(): array
    {
        $default = config('drawio.default_node_style', 'rounded=0;whiteSpace=wrap;html=1;');

        return collect(config('node_types'))
            ->mapWithKeys(fn (array $type) => [$type['type'] => $type['drawio_shape'] ?? $default])
            ->all();
    }

    public function detectArrowType(array $edge): string
    {
        $hasStart = ! empty($edge['markerStart']);
        $hasEnd = ! empty($edge['markerEnd']);
        $dashed = ! empty($edge['style']['strokeDasharray']);

        if ($hasStart && $hasEnd) {
            return $dashed ? 'bi_dashed' : 'bi';
        }
        if ($hasEnd) {
            return $dashed ? 'mono_dashed' : 'mono';
        }
        if ($hasStart) {
            return $dashed ? 'mono_start_dashed' : 'mono_start';
        }

        return $dashed ? 'none_dashed' : 'none';
    }

    public function resolveEdgeStyle(string $arrowType): string
    {
        $arrows = config('drawio.arrows', []);

        return $arrows[$arrowType] ?? config('drawio.default_arrow', 'endArrow=classic;html=1;');
    }

    public function resolveNodeSize(array $node): array
    {
        $isNote = ($node['type'] ?? null) === 'note';
        $default = $isNote
            ? config('drawio.note_node_size', ['width' => 180, 'height' => 100])
            : config('drawio.default_node_size', ['width' => 120, 'height' => 60]);

        $width = $node['width']
            ?? $node['dimensions']['width']
            ?? $default['width'];

        $height = $node['height']
            ?? $node['dimensions']['height']
            ?? $default['height'];

        return [
            'width' => (int) round((float) $width),
            'height' => (int) round((float) $height),
        ];
    }

    public function convertNode(array $node, string $style): string
    {
        if (empty($node['id'])) {
            throw new \InvalidArgumentException('A node must have a non-empty id to be converted to draw.io.');
        }

        $safeId = $this->nodeId($node['id']);
        $rawLabel = trim($node['data']['label'] ?? '');
        $escapedLabel = $rawLabel !== '' ? $this->escapeLabel($rawLabel) : '';
        $escapedStyle = $this->escapeXml($style);

        $x = (int) round((float) ($node['position']['x'] ?? 0));
        $y = (int) round((float) ($node['position']['y'] ?? 0));

        ['width' => $width, 'height' => $height] = $this->resolveNodeSize($node);

        return '<mxCell id="'.$safeId.'" value="'.$escapedLabel.'" style="'.$escapedStyle.'" vertex="1" parent="1">'
            ."\n          ".'<mxGeometry x="'.$x.'" y="'.$y.'" width="'.$width.'" height="'.$height.'" as="geometry" />'
            ."\n        ".'</mxCell>';
    }

    public function convertEdge(array $edge, int $index): string
    {
        if (empty($edge['source']) || empty($edge['target'])) {
            throw new \InvalidArgumentException('An edge must have a non-empty source and target to be converted to draw.io.');
        }

        $edgeId = ! empty($edge['id'])
            ? $this->edgeId($edge['id'])
            : 'e_idx_'.$index;

        $source = $this->nodeId($edge['source']);
        $target = $this->nodeId($edge['target']);

        $arrowType = $this->detectArrowType($edge);
        $style = $this->resolveEdgeStyle($arrowType);
        $escapedStyle = $this->escapeXml($style);

        $rawLabel = trim($edge['label'] ?? '');
        $escapedLabel = $rawLabel !== '' ? $this->escapeLabel($rawLabel) : '';

        return '<mxCell id="'.$edgeId.'" value="'.$escapedLabel.'" style="'.$escapedStyle.'" edge="1" parent="1" source="'.$source.'" target="'.$target.'">'
            ."\n          ".'<mxGeometry relative="1" as="geometry" />'
            ."\n        ".'</mxCell>';
    }

    public function exportSketch(array $canvasState): string
    {
        $nodes = $canvasState['nodes'] ?? [];
        $edges = $canvasState['edges'] ?? [];

        $shapeMap = $this->buildShapeMap();
        $defaultStyle = config('drawio.default_node_style', 'rounded=0;whiteSpace=wrap;html=1;');

        $nodeCells = collect($nodes)
            ->filter(fn (array $node) => ! empty($node['id']))
            ->map(fn (array $node) => '        '.$this->convertNode(
                $node,
                $shapeMap[$node['type'] ?? ''] ?? $defaultStyle,
            ));

        $edgeCells = collect($edges)
            ->filter(fn (array $edge) => ! empty($edge['source']) && ! empty($edge['target']))
            ->values()
            ->map(fn (array $edge, int $index) => '        '.$this->convertEdge($edge, $index));

        $body = $nodeCells->merge($edgeCells)->join("\n");

        $header = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<mxfile host="app.diagrams.net" type="device">'."\n"
            .'  <diagram name="Schets" id="archify-sketch">'."\n"
            .'    <mxGraphModel dx="1422" dy="757" grid="1" gridSize="10" guides="1" tooltips="1" connect="1" arrows="1" fold="1" page="1" pageScale="1" pageWidth="850" pageHeight="1100" math="0" shadow="0">'."\n"
            .'      <root>'."\n"
            .'        <mxCell id="0" />'."\n"
            .'        <mxCell id="1" parent="0" />';

        $footer = '      </root>'."\n"
            .'    </mxGraphModel>'."\n"
            .'  </diagram>'."\n"
            .'</mxfile>';

        return $body !== ''
            ? $header."\n".$body."\n".$footer
            : $header."\n".$footer;
    }
}
