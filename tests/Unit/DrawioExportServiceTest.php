<?php

use App\Services\DrawioExportService;

beforeEach(function () {
    $this->service = new DrawioExportService;
});

test('sanitizeId laat een geldige UUID-stijl ID ongewijzigd', function () {
    expect($this->service->sanitizeId('abc-123_XYZ'))->toBe('abc-123_XYZ');
});

test('sanitizeId vervangt spaties en speciale tekens door underscores', function () {
    expect($this->service->sanitizeId('mijn node.1'))->toBe('mijn_node_1');
});

test('escapeXml escaped XML-speciale tekens', function () {
    expect($this->service->escapeXml('Server "A" <prod> & test'))
        ->toBe('Server &quot;A&quot; &lt;prod&gt; &amp; test');
});

test('escapeLabel vervangt newlines door &#10;', function () {
    expect($this->service->escapeLabel("Regel 1\nRegel 2"))
        ->toBe('Regel 1&#10;Regel 2');
});

test('escapeLabel normaliseert CRLF en CR naar &#10;', function () {
    expect($this->service->escapeLabel("a\r\nb\rc"))
        ->toBe('a&#10;b&#10;c');
});

test('buildShapeMap leest drawio_shape uit node_types config', function () {
    $map = $this->service->buildShapeMap();

    expect($map)->toBeArray()
        ->and($map['rectangle'])->toContain('rounded=0')
        ->and($map['database'])->toContain('cylinder3')
        ->and($map['application'])->toContain('hexagon')
        ->and($map['user'])->toContain('umlActor')
        ->and($map['note'])->toContain('shape=note');
});

test('detectArrowType geeft "none" terug zonder markers', function () {
    expect($this->service->detectArrowType(['source' => 'a', 'target' => 'b']))->toBe('none');
});

test('detectArrowType geeft "mono" terug bij markerEnd', function () {
    $edge = ['source' => 'a', 'target' => 'b', 'markerEnd' => ['type' => 'arrowclosed']];
    expect($this->service->detectArrowType($edge))->toBe('mono');
});

test('detectArrowType geeft "mono_start" terug bij alleen markerStart', function () {
    $edge = ['source' => 'a', 'target' => 'b', 'markerStart' => ['type' => 'arrowclosed']];
    expect($this->service->detectArrowType($edge))->toBe('mono_start');
});

test('detectArrowType geeft "bi" terug bij beide markers', function () {
    $edge = [
        'source' => 'a',
        'target' => 'b',
        'markerStart' => ['type' => 'arrowclosed'],
        'markerEnd' => ['type' => 'arrowclosed'],
    ];
    expect($this->service->detectArrowType($edge))->toBe('bi');
});

test('detectArrowType geeft "_dashed"-variant terug voor stippellijnen', function () {
    $edge = [
        'source' => 'a',
        'target' => 'b',
        'markerEnd' => ['type' => 'arrowclosed'],
        'style' => ['strokeDasharray' => '6 4'],
    ];
    expect($this->service->detectArrowType($edge))->toBe('mono_dashed');
});

test('resolveEdgeStyle geeft drawio edge stijl terug voor mono', function () {
    expect($this->service->resolveEdgeStyle('mono'))
        ->toBe('edgeStyle=orthogonalEdgeStyle;endArrow=classic;html=1;');
});

test('resolveEdgeStyle geeft startArrow stijl terug voor mono_start', function () {
    expect($this->service->resolveEdgeStyle('mono_start'))
        ->toBe('edgeStyle=orthogonalEdgeStyle;startArrow=classic;endArrow=none;html=1;');
});

test('resolveEdgeStyle geeft dashed stijl terug voor bi_dashed', function () {
    expect($this->service->resolveEdgeStyle('bi_dashed'))
        ->toBe('edgeStyle=orthogonalEdgeStyle;endArrow=classic;startArrow=classic;html=1;dashed=1;');
});

test('resolveEdgeStyle valt terug op default voor onbekend type', function () {
    expect($this->service->resolveEdgeStyle('onbekend'))
        ->toBe('edgeStyle=orthogonalEdgeStyle;endArrow=classic;html=1;');
});

test('resolveEdgeStyle gebruikt orthogonale lijnen voor alle pijl-typen', function () {
    foreach (['none', 'mono', 'mono_start', 'bi', 'none_dashed', 'mono_dashed', 'mono_start_dashed', 'bi_dashed'] as $type) {
        expect($this->service->resolveEdgeStyle($type))->toContain('edgeStyle=orthogonalEdgeStyle');
    }
});

test('resolveNodeSize geeft expliciete width en height terug', function () {
    $node = ['type' => 'rectangle', 'width' => 200, 'height' => 80];
    expect($this->service->resolveNodeSize($node))->toBe(['width' => 200, 'height' => 80]);
});

test('resolveNodeSize leest dimensions wanneer top-level width/height ontbreken', function () {
    $node = ['type' => 'rectangle', 'dimensions' => ['width' => 150.7, 'height' => 75.2]];
    expect($this->service->resolveNodeSize($node))->toBe(['width' => 151, 'height' => 75]);
});

test('resolveNodeSize valt terug op default voor reguliere nodes', function () {
    expect($this->service->resolveNodeSize(['type' => 'rectangle']))
        ->toBe(['width' => 120, 'height' => 60]);
});

test('resolveNodeSize valt terug op grotere default voor note nodes', function () {
    expect($this->service->resolveNodeSize(['type' => 'note']))
        ->toBe(['width' => 180, 'height' => 100]);
});

test('resolveNodeSize gebruikt een smallere default voor user nodes zodat de actor niet uitrekt', function () {
    expect($this->service->resolveNodeSize(['type' => 'user']))
        ->toBe(['width' => 60, 'height' => 80]);
});

test('convertNode bouwt een mxCell met label, stijl, positie en grootte', function () {
    $node = [
        'id' => 'n1',
        'type' => 'rectangle',
        'position' => ['x' => 100, 'y' => 50],
        'data' => ['label' => 'Blok'],
    ];

    $xml = $this->service->convertNode($node, 'rounded=0;whiteSpace=wrap;html=1;');

    expect($xml)
        ->toContain('<mxCell id="n_n1"')
        ->toContain('value="Blok"')
        ->toContain('style="rounded=0;whiteSpace=wrap;html=1;"')
        ->toContain('vertex="1"')
        ->toContain('parent="1"')
        ->toContain('<mxGeometry x="100" y="50" width="120" height="60" as="geometry" />');
});

test('convertNode prefixt numerieke node ids om collision met drawio root cellen te voorkomen', function () {
    $node = [
        'id' => '1',
        'type' => 'rectangle',
        'position' => ['x' => 0, 'y' => 0],
        'data' => ['label' => 'X'],
    ];

    expect($this->service->convertNode($node, 'rounded=0;'))->toContain('id="n_1"');
});

test('convertNode laat het label leeg wanneer data.label ontbreekt', function () {
    $node = ['id' => 'abc-1', 'position' => ['x' => 0, 'y' => 0], 'data' => []];
    expect($this->service->convertNode($node, 'rounded=0;'))
        ->toContain('value=""')
        ->not->toContain('value="abc-1"');
});

test('convertNode laat het label leeg wanneer data.label een lege string is', function () {
    $node = ['id' => 'abc-1', 'position' => ['x' => 0, 'y' => 0], 'data' => ['label' => '']];
    expect($this->service->convertNode($node, 'rounded=0;'))->toContain('value=""');
});

test('convertNode laat het label leeg wanneer data.label alleen whitespace bevat', function () {
    $node = ['id' => 'abc-1', 'position' => ['x' => 0, 'y' => 0], 'data' => ['label' => '   ']];
    expect($this->service->convertNode($node, 'rounded=0;'))->toContain('value=""');
});

test('convertNode escaped speciale tekens in het label', function () {
    $node = [
        'id' => 'n1',
        'position' => ['x' => 0, 'y' => 0],
        'data' => ['label' => 'Server "A" <prod>'],
    ];

    expect($this->service->convertNode($node, 'rounded=0;'))
        ->toContain('value="Server &quot;A&quot; &lt;prod&gt;"');
});

test('convertNode behoudt newlines als &#10; in het label', function () {
    $node = [
        'id' => 'n1',
        'position' => ['x' => 0, 'y' => 0],
        'data' => ['label' => "Regel 1\nRegel 2"],
    ];

    expect($this->service->convertNode($node, 'rounded=0;'))
        ->toContain('value="Regel 1&#10;Regel 2"');
});

test('convertNode sanitizeert de node id', function () {
    $node = ['id' => 'mijn node', 'position' => ['x' => 0, 'y' => 0], 'data' => ['label' => 'X']];
    expect($this->service->convertNode($node, 'rounded=0;'))->toContain('id="n_mijn_node"');
});

test('convertNode gooit een InvalidArgumentException wanneer id ontbreekt', function () {
    expect(fn () => $this->service->convertNode(['data' => ['label' => 'Label']], 'rounded=0;'))
        ->toThrow(InvalidArgumentException::class, 'non-empty id');
});

test('convertEdge bouwt een mxCell met source, target en stijl', function () {
    $edge = [
        'id' => 'e1',
        'source' => 'a',
        'target' => 'b',
        'markerEnd' => ['type' => 'arrowclosed'],
    ];

    $xml = $this->service->convertEdge($edge, 0);

    expect($xml)
        ->toContain('<mxCell id="e_e1"')
        ->toContain('style="edgeStyle=orthogonalEdgeStyle;endArrow=classic;html=1;"')
        ->toContain('edge="1"')
        ->toContain('source="n_a"')
        ->toContain('target="n_b"')
        ->toContain('<mxGeometry relative="1" as="geometry" />');
});

test('convertEdge genereert een fallback id wanneer id ontbreekt', function () {
    $edge = ['source' => 'a', 'target' => 'b'];
    expect($this->service->convertEdge($edge, 3))->toContain('id="e_idx_3"');
});

test('convertEdge zet het edge-label als value', function () {
    $edge = [
        'id' => 'e1',
        'source' => 'a',
        'target' => 'b',
        'markerEnd' => ['type' => 'arrowclosed'],
        'label' => 'stuurt data',
    ];

    expect($this->service->convertEdge($edge, 0))->toContain('value="stuurt data"');
});

test('convertEdge gooit een InvalidArgumentException zonder source of target', function () {
    expect(fn () => $this->service->convertEdge(['target' => 'b'], 0))
        ->toThrow(InvalidArgumentException::class, 'non-empty source and target');
});

test('exportSketch produceert een geldige mxfile XML met header en root cellen', function () {
    $xml = $this->service->exportSketch(['nodes' => [], 'edges' => []]);

    expect($xml)
        ->toStartWith('<?xml version="1.0" encoding="UTF-8"?>')
        ->toContain('<mxfile')
        ->toContain('<diagram')
        ->toContain('<mxGraphModel')
        ->toContain('<mxCell id="0" />')
        ->toContain('<mxCell id="1" parent="0" />')
        ->toContain('</mxfile>');
});

test('exportSketch is parseerbaar als geldige XML', function () {
    $xml = $this->service->exportSketch([
        'nodes' => [
            ['id' => 'n1', 'type' => 'server', 'position' => ['x' => 10, 'y' => 20], 'data' => ['label' => 'API']],
        ],
        'edges' => [],
    ]);

    $previous = libxml_use_internal_errors(true);
    $doc = simplexml_load_string($xml);
    libxml_use_internal_errors($previous);

    expect($doc)->not->toBeFalse();
});

test('exportSketch bevat de juiste shape-stijl per node type', function () {
    $xml = $this->service->exportSketch([
        'nodes' => [
            ['id' => 'srv', 'type' => 'server',   'position' => ['x' => 0, 'y' => 0], 'data' => ['label' => 'API']],
            ['id' => 'db',  'type' => 'database', 'position' => ['x' => 0, 'y' => 0], 'data' => ['label' => 'DB']],
            ['id' => 'usr', 'type' => 'user',     'position' => ['x' => 0, 'y' => 0], 'data' => ['label' => 'U']],
        ],
        'edges' => [],
    ]);

    expect($xml)
        ->toContain('id="n_srv"')
        ->toContain('rounded=1')
        ->toContain('id="n_db"')
        ->toContain('cylinder3')
        ->toContain('id="n_usr"')
        ->toContain('umlActor');
});

test('exportSketch slaat nodes zonder id stilzwijgend over', function () {
    $xml = $this->service->exportSketch([
        'nodes' => [
            ['id' => 'n1', 'type' => 'rectangle', 'position' => ['x' => 0, 'y' => 0], 'data' => ['label' => 'A']],
            ['type' => 'rectangle', 'position' => ['x' => 0, 'y' => 0], 'data' => ['label' => 'Geen ID']],
        ],
        'edges' => [],
    ]);

    expect($xml)
        ->toContain('value="A"')
        ->not->toContain('Geen ID');
});

test('exportSketch slaat edges zonder source of target stilzwijgend over', function () {
    $xml = $this->service->exportSketch([
        'nodes' => [
            ['id' => 'a', 'type' => 'rectangle', 'position' => ['x' => 0, 'y' => 0], 'data' => ['label' => 'A']],
            ['id' => 'b', 'type' => 'rectangle', 'position' => ['x' => 0, 'y' => 0], 'data' => ['label' => 'B']],
        ],
        'edges' => [
            ['id' => 'e1', 'source' => 'a', 'target' => 'b', 'markerEnd' => ['type' => 'arrowclosed']],
            ['id' => 'e2', 'target' => 'b'],
            ['id' => 'e3', 'source' => 'a'],
        ],
    ]);

    expect($xml)
        ->toContain('id="e_e1"')
        ->not->toContain('id="e_e2"')
        ->not->toContain('id="e_e3"');
});

test('exportSketch bevat zowel nodes als edges in de uitvoer', function () {
    $xml = $this->service->exportSketch([
        'nodes' => [
            ['id' => 'a', 'type' => 'server',   'position' => ['x' => 0, 'y' => 0], 'data' => ['label' => 'API']],
            ['id' => 'b', 'type' => 'database', 'position' => ['x' => 200, 'y' => 0], 'data' => ['label' => 'DB']],
        ],
        'edges' => [
            ['id' => 'e1', 'source' => 'a', 'target' => 'b', 'markerEnd' => ['type' => 'arrowclosed']],
        ],
    ]);

    expect($xml)
        ->toContain('id="n_a"')
        ->toContain('id="n_b"')
        ->toContain('id="e_e1"')
        ->toContain('source="n_a"')
        ->toContain('target="n_b"');
});

test('exportSketch produceert geen cellen die collideren met drawio root cellen voor numerieke ids', function () {
    $xml = $this->service->exportSketch([
        'nodes' => [
            ['id' => '1', 'type' => 'rectangle', 'position' => ['x' => 0, 'y' => 0], 'data' => ['label' => 'A']],
            ['id' => '2', 'type' => 'rectangle', 'position' => ['x' => 0, 'y' => 0], 'data' => ['label' => 'B']],
        ],
        'edges' => [
            ['id' => 'e1-2', 'source' => '1', 'target' => '2'],
        ],
    ]);

    $matches = [];
    preg_match_all('/<mxCell\s+id="([^"]+)"/', $xml, $matches);
    $ids = $matches[1];

    expect($ids)->toBe(['0', '1', 'n_1', 'n_2', 'e_e1-2']);
    expect(count(array_unique($ids)))->toBe(count($ids));
});

test('exportSketch werkt met een lege canvas state', function () {
    $xml = $this->service->exportSketch([]);

    expect($xml)
        ->toContain('<mxfile')
        ->toContain('<mxCell id="0" />')
        ->toContain('<mxCell id="1" parent="0" />');
});
