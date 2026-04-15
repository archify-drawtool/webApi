<?php

use App\Services\MermaidExportService;

beforeEach(function () {
    $this->service = new MermaidExportService;
});

// ─── sanitizeId ───────────────────────────────────────────────────────────────

test('sanitizeId laat geldige UUID-stijl ID ongewijzigd', function () {
    expect($this->service->sanitizeId('abc-123_XYZ'))->toBe('abc-123_XYZ');
});

test('sanitizeId vervangt spaties door underscores', function () {
    expect($this->service->sanitizeId('mijn node'))->toBe('mijn_node');
});

test('sanitizeId vervangt speciale tekens door underscores', function () {
    expect($this->service->sanitizeId('node#1%2'))->toBe('node_1_2');
});

test('sanitizeId vervangt punten door underscores', function () {
    expect($this->service->sanitizeId('node.1.2'))->toBe('node_1_2');
});

// ─── escapeLabel ──────────────────────────────────────────────────────────────

test('escapeLabel laat gewone tekst ongewijzigd', function () {
    expect($this->service->escapeLabel('Mijn server'))->toBe('Mijn server');
});

test('escapeLabel escaped dubbele aanhalingstekens', function () {
    expect($this->service->escapeLabel('Server "A"'))->toBe('Server \\"A\\"');
});

test('escapeLabel escaped backslashes', function () {
    expect($this->service->escapeLabel('pad\\naar\\map'))->toBe('pad\\\\naar\\\\map');
});

test('escapeLabel escaped backslashes voor aanhalingstekens in de juiste volgorde', function () {
    expect($this->service->escapeLabel('\\"'))->toBe('\\\\\\"');
});

test('escapeLabel laat dollartekens ongemoeid (geen speciaal teken in Mermaid)', function () {
    expect($this->service->escapeLabel('$server'))->toBe('$server');
});

// ─── convertNode ──────────────────────────────────────────────────────────────

test('convertNode geeft rechthoek voor shape rectangle', function () {
    $node = ['id' => 'n1', 'data' => ['label' => 'Rechthoek']];
    expect($this->service->convertNode($node, 'rectangle'))->toBe('n1["Rechthoek"]');
});

test('convertNode geeft subroutine (dubbele rand) voor shape subroutine', function () {
    $node = ['id' => 'n1', 'data' => ['label' => 'Server']];
    expect($this->service->convertNode($node, 'subroutine'))->toBe('n1[["Server"]]');
});

test('convertNode geeft cilinder voor shape cylinder', function () {
    $node = ['id' => 'n1', 'data' => ['label' => 'Database']];
    expect($this->service->convertNode($node, 'cylinder'))->toBe('n1[("Database")]');
});

test('convertNode geeft hexagon voor shape hexagon', function () {
    $node = ['id' => 'n1', 'data' => ['label' => 'Applicatie']];
    expect($this->service->convertNode($node, 'hexagon'))->toBe('n1{{"Applicatie"}}');
});

test('convertNode geeft cirkel voor shape circle', function () {
    $node = ['id' => 'n1', 'data' => ['label' => 'Gebruiker']];
    expect($this->service->convertNode($node, 'circle'))->toBe('n1(("Gebruiker"))');
});

test('convertNode geeft afgeronde rechthoek voor shape rounded', function () {
    $node = ['id' => 'n1', 'data' => ['label' => 'Afgerond']];
    expect($this->service->convertNode($node, 'rounded'))->toBe('n1("Afgerond")');
});

test('convertNode valt terug op rechthoek voor onbekende shape', function () {
    $node = ['id' => 'n1', 'data' => ['label' => 'Label']];
    expect($this->service->convertNode($node, 'onbekend'))->toBe('n1["Label"]');
});

test('convertNode gebruikt node id als label wanneer data.label ontbreekt', function () {
    $node = ['id' => 'abc-1', 'data' => []];
    expect($this->service->convertNode($node, 'rectangle'))->toBe('abc-1["abc-1"]');
});

test('convertNode gebruikt node id als label wanneer data.label een lege string is', function () {
    $node = ['id' => 'abc-1', 'data' => ['label' => '']];
    expect($this->service->convertNode($node, 'rectangle'))->toBe('abc-1["abc-1"]');
});

test('convertNode gebruikt node id als label wanneer data.label alleen whitespace bevat', function () {
    $node = ['id' => 'abc-1', 'data' => ['label' => '   ']];
    expect($this->service->convertNode($node, 'rectangle'))->toBe('abc-1["abc-1"]');
});

test('convertNode sanitizeert de node ID', function () {
    $node = ['id' => 'mijn node', 'data' => ['label' => 'Label']];
    expect($this->service->convertNode($node, 'rectangle'))->toBe('mijn_node["Label"]');
});

test('convertNode escaped aanhalingstekens in het label', function () {
    $node = ['id' => 'n1', 'data' => ['label' => 'Server "A"']];
    expect($this->service->convertNode($node, 'rectangle'))->toBe('n1["Server \\"A\\""]');
});

test('convertNode gooit een InvalidArgumentException wanneer id ontbreekt', function () {
    expect(fn () => $this->service->convertNode(['data' => ['label' => 'Label']], 'rectangle'))
        ->toThrow(InvalidArgumentException::class, 'non-empty id');
});

test('convertNode gooit een InvalidArgumentException wanneer id een lege string is', function () {
    expect(fn () => $this->service->convertNode(['id' => '', 'data' => []], 'rectangle'))
        ->toThrow(InvalidArgumentException::class, 'non-empty id');
});

// ─── buildShapeMap ────────────────────────────────────────────────────────────

test('buildShapeMap geeft een array terug met type als sleutel en shape als waarde', function () {
    $map = $this->service->buildShapeMap();

    expect($map)->toBeArray()
        ->and($map['rectangle'])->toBe('rectangle')
        ->and($map['server'])->toBe('subroutine')
        ->and($map['database'])->toBe('cylinder')
        ->and($map['application'])->toBe('hexagon')
        ->and($map['user'])->toBe('circle');
});

// ─── nodeToMermaid ────────────────────────────────────────────────────────────

test('nodeToMermaid gebruikt cylinder voor type database', function () {
    $node = ['id' => 'db-1', 'type' => 'database', 'data' => ['label' => 'Mijn DB']];
    expect($this->service->nodeToMermaid($node))->toBe('db-1[("Mijn DB")]');
});

test('nodeToMermaid gebruikt circle voor type user', function () {
    $node = ['id' => 'u-1', 'type' => 'user', 'data' => ['label' => 'Beheerder']];
    expect($this->service->nodeToMermaid($node))->toBe('u-1(("Beheerder"))');
});

test('nodeToMermaid gebruikt subroutine voor type server', function () {
    $node = ['id' => 's-1', 'type' => 'server', 'data' => ['label' => 'API']];
    expect($this->service->nodeToMermaid($node))->toBe('s-1[["API"]]');
});

test('nodeToMermaid gebruikt hexagon voor type application', function () {
    $node = ['id' => 'app-1', 'type' => 'application', 'data' => ['label' => 'Frontend']];
    expect($this->service->nodeToMermaid($node))->toBe('app-1{{"Frontend"}}');
});

test('nodeToMermaid gebruikt rectangle voor type rectangle', function () {
    $node = ['id' => 'r-1', 'type' => 'rectangle', 'data' => ['label' => 'Blok']];
    expect($this->service->nodeToMermaid($node))->toBe('r-1["Blok"]');
});

test('nodeToMermaid valt terug op rectangle voor onbekend type', function () {
    $node = ['id' => 'x-1', 'type' => 'onbekend', 'data' => ['label' => 'Label']];
    expect($this->service->nodeToMermaid($node))->toBe('x-1["Label"]');
});

test('nodeToMermaid valt terug op rectangle wanneer type ontbreekt', function () {
    $node = ['id' => 'x-1', 'data' => ['label' => 'Label']];
    expect($this->service->nodeToMermaid($node))->toBe('x-1["Label"]');
});

// ─── exportNodes ─────────────────────────────────────────────────────────────

test('exportNodes geeft een volledige Mermaid flowchart terug voor meerdere nodes', function () {
    $nodes = [
        ['id' => 'srv-1', 'type' => 'server',   'data' => ['label' => 'API Gateway']],
        ['id' => 'db-1',  'type' => 'database',  'data' => ['label' => 'Database']],
    ];

    $result = $this->service->exportNodes($nodes);

    expect($result)->toBe("flowchart TD\n  srv-1[[\"API Gateway\"]]\n  db-1[(\"Database\")]");
});

test('exportNodes geeft "flowchart TD" terug zonder trailing newline voor lege nodes', function () {
    expect($this->service->exportNodes([]))->toBe('flowchart TD');
});

test('exportNodes slaat nodes zonder id stilzwijgend over', function () {
    $nodes = [
        ['id' => 'n1', 'type' => 'server', 'data' => ['label' => 'Geldig']],
        ['type' => 'server', 'data' => ['label' => 'Geen ID']],
        ['id' => '',   'type' => 'server', 'data' => ['label' => 'Leeg ID']],
    ];

    $result = $this->service->exportNodes($nodes);

    expect($result)->toBe("flowchart TD\n  n1[[\"Geldig\"]]");
});

test('exportNodes bouwt de config shape map slechts eenmaal op', function () {
    // Als exportNodes de map eenmaal bouwt, moeten alle nodes correct de juiste shape krijgen.
    $nodes = [
        ['id' => 'db-1',  'type' => 'database', 'data' => ['label' => 'DB']],
        ['id' => 'usr-1', 'type' => 'user',     'data' => ['label' => 'Gebruiker']],
        ['id' => 'srv-1', 'type' => 'server',   'data' => ['label' => 'Server']],
    ];

    $result = $this->service->exportNodes($nodes);

    expect($result)
        ->toContain('db-1[("DB")]')
        ->toContain('usr-1(("Gebruiker"))')
        ->toContain('srv-1[["Server"]]');
});

// ─── detectArrowType ──────────────────────────────────────────────────────────

test('detectArrowType geeft "none" terug wanneer er geen markers zijn', function () {
    expect($this->service->detectArrowType(['source' => 'a', 'target' => 'b']))->toBe('none');
});

test('detectArrowType geeft "mono" terug wanneer alleen markerEnd aanwezig is', function () {
    $edge = ['source' => 'a', 'target' => 'b', 'markerEnd' => ['type' => 'arrowclosed']];
    expect($this->service->detectArrowType($edge))->toBe('mono');
});

test('detectArrowType geeft "bi" terug wanneer zowel markerStart als markerEnd aanwezig zijn', function () {
    $edge = [
        'source'      => 'a',
        'target'      => 'b',
        'markerStart' => ['type' => 'arrowclosed'],
        'markerEnd'   => ['type' => 'arrowclosed'],
    ];
    expect($this->service->detectArrowType($edge))->toBe('bi');
});

test('detectArrowType geeft "none" terug wanneer markerEnd null is', function () {
    $edge = ['source' => 'a', 'target' => 'b', 'markerEnd' => null];
    expect($this->service->detectArrowType($edge))->toBe('none');
});

// ─── resolveArrow ─────────────────────────────────────────────────────────────

test('resolveArrow geeft "---" terug voor none', function () {
    expect($this->service->resolveArrow('none'))->toBe('---');
});

test('resolveArrow geeft "-->" terug voor mono', function () {
    expect($this->service->resolveArrow('mono'))->toBe('-->');
});

test('resolveArrow geeft "<-->" terug voor bi', function () {
    expect($this->service->resolveArrow('bi'))->toBe('<-->');
});

test('resolveArrow valt terug op default_arrow voor onbekend type', function () {
    expect($this->service->resolveArrow('onbekend'))->toBe('-->');
});

// ─── convertEdge ─────────────────────────────────────────────────────────────

test('convertEdge geeft een lijn zonder pijl terug voor een edge zonder markers', function () {
    $edge = ['source' => 'a', 'target' => 'b'];
    expect($this->service->convertEdge($edge))->toBe('a --- b');
});

test('convertEdge geeft een pijl terug voor een edge met markerEnd', function () {
    $edge = ['source' => 'a', 'target' => 'b', 'markerEnd' => ['type' => 'arrowclosed']];
    expect($this->service->convertEdge($edge))->toBe('a --> b');
});

test('convertEdge geeft een bidirectionele pijl terug voor een edge met beide markers', function () {
    $edge = [
        'source'      => 'a',
        'target'      => 'b',
        'markerStart' => ['type' => 'arrowclosed'],
        'markerEnd'   => ['type' => 'arrowclosed'],
    ];
    expect($this->service->convertEdge($edge))->toBe('a <--> b');
});

test('convertEdge voegt het label toe tussen pipes wanneer een label aanwezig is', function () {
    $edge = ['source' => 'a', 'target' => 'b', 'markerEnd' => ['type' => 'arrowclosed'], 'label' => 'API call'];
    expect($this->service->convertEdge($edge))->toBe('a -->|"API call"| b');
});

test('convertEdge escaped aanhalingstekens in het edge label', function () {
    $edge = ['source' => 'a', 'target' => 'b', 'markerEnd' => ['type' => 'arrowclosed'], 'label' => 'stuurt "data"'];
    expect($this->service->convertEdge($edge))->toBe('a -->|"stuurt \\"data\\""| b');
});

test('convertEdge exporteert zonder label wanneer het label leeg is', function () {
    $edge = ['source' => 'a', 'target' => 'b', 'markerEnd' => ['type' => 'arrowclosed'], 'label' => ''];
    expect($this->service->convertEdge($edge))->toBe('a --> b');
});

test('convertEdge exporteert zonder label wanneer het label alleen whitespace bevat', function () {
    $edge = ['source' => 'a', 'target' => 'b', 'markerEnd' => ['type' => 'arrowclosed'], 'label' => '   '];
    expect($this->service->convertEdge($edge))->toBe('a --> b');
});

test('convertEdge sanitiseert source en target IDs', function () {
    $edge = ['source' => 'node 1', 'target' => 'node 2'];
    expect($this->service->convertEdge($edge))->toBe('node_1 --- node_2');
});

test('convertEdge gooit een InvalidArgumentException wanneer source ontbreekt', function () {
    expect(fn () => $this->service->convertEdge(['target' => 'b']))
        ->toThrow(\InvalidArgumentException::class, 'source and target');
});

test('convertEdge gooit een InvalidArgumentException wanneer target ontbreekt', function () {
    expect(fn () => $this->service->convertEdge(['source' => 'a']))
        ->toThrow(\InvalidArgumentException::class, 'source and target');
});

// ─── exportSketch ─────────────────────────────────────────────────────────────

test('exportSketch combineert nodes en edges in één flowchart', function () {
    $canvasState = [
        'nodes' => [
            ['id' => 'srv-1', 'type' => 'server',   'data' => ['label' => 'API']],
            ['id' => 'db-1',  'type' => 'database',  'data' => ['label' => 'DB']],
        ],
        'edges' => [
            ['id' => 'e1', 'source' => 'srv-1', 'target' => 'db-1', 'markerEnd' => ['type' => 'arrowclosed']],
        ],
    ];

    $result = $this->service->exportSketch($canvasState);

    expect($result)
        ->toContain('flowchart TD')
        ->toContain('srv-1[["API"]]')
        ->toContain('db-1[("DB")]')
        ->toContain('srv-1 --> db-1');
});

test('exportSketch geeft "flowchart TD" terug voor een lege canvas state', function () {
    expect($this->service->exportSketch([]))->toBe('flowchart TD');
});

test('exportSketch geeft "flowchart TD" terug voor een canvas met lege nodes en edges', function () {
    expect($this->service->exportSketch(['nodes' => [], 'edges' => []]))->toBe('flowchart TD');
});

test('exportSketch slaat edges zonder source of target stilzwijgend over', function () {
    $canvasState = [
        'nodes' => [
            ['id' => 'n1', 'type' => 'server', 'data' => ['label' => 'Server']],
        ],
        'edges' => [
            ['id' => 'e1', 'source' => 'n1', 'target' => 'n2', 'markerEnd' => ['type' => 'arrowclosed']],
            ['id' => 'e2', 'source' => '',   'target' => 'n1'],
            ['id' => 'e3', 'target' => 'n1'],
        ],
    ];

    $result = $this->service->exportSketch($canvasState);

    expect($result)
        ->toContain('n1[["Server"]]')
        ->toContain('n1 --> n2')
        ->not->toContain('e2')
        ->not->toContain('e3');
});

test('exportSketch plaatst node-declaraties voor edge-declaraties', function () {
    $canvasState = [
        'nodes' => [['id' => 'a', 'type' => 'server', 'data' => ['label' => 'A']]],
        'edges' => [['source' => 'a', 'target' => 'b', 'markerEnd' => ['type' => 'arrowclosed']]],
    ];

    $lines = explode("\n", $this->service->exportSketch($canvasState));

    expect($lines[0])->toBe('flowchart TD')
        ->and($lines[1])->toContain('a[["A"]]')
        ->and($lines[2])->toContain('a --> b');
});
