<?php
// Trich route()/URI/service ma moi file test goi toi -> storage/app/test-refs.json
$root = dirname(__DIR__, 2);
$refs = [];
$files = array_merge(
    glob($root.'/tests/Feature/*.php'),
    glob($root.'/tests/Feature/Concerns/*.php') ?: [],
    glob($root.'/tests/Unit/*.php') ?: []
);
foreach ($files as $f) {
    $c = file_get_contents($f);
    $base = basename($f, '.php');
    preg_match_all('/route\(\s*[\'"]([\w.\-]+)[\'"]/', $c, $m1);
    preg_match_all('/->(?:get|post|put|patch|delete|getJson|postJson|putJson|deleteJson|ajax|json|call|request|followingRedirects|from)\(\s*[\'"]([^\'"]+)[\'"]/', $c, $m2);
    preg_match_all('/App\\\\(?:Services|Http\\\\Controllers|Models|Exports|Support|Rules)\\\\(\w+)/', $c, $m3);
    $refs[$base] = [
        'routes' => array_values(array_unique($m1[1] ?? [])),
        'uris' => array_values(array_unique(array_filter($m2[1] ?? [], fn($u) => str_starts_with($u, '/') || str_starts_with($u, 'http')))),
        'classes' => array_values(array_unique($m3[1] ?? [])),
    ];
}
file_put_contents($root.'/storage/app/test-refs.json', json_encode($refs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo 'files='.count($refs)."\n";
