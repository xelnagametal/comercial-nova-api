<?php

use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Yaml\Yaml;

it('documents every api route in the static openapi spec and nothing else', function () {
    $spec = Yaml::parseFile(public_path('swagger/openapi.yaml'));

    $documentado = collect($spec['paths'])
        ->flatMap(fn (array $operaciones, string $ruta) => collect($operaciones)
            ->keys()
            ->reject(fn (string $clave) => $clave === 'parameters')
            ->map(fn (string $metodo) => strtoupper($metodo).' '.$ruta))
        ->sort()
        ->values()
        ->all();

    $registrado = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RouteDefinition $ruta) => str_starts_with($ruta->uri(), 'api/'))
        ->flatMap(fn (RouteDefinition $ruta) => collect($ruta->methods())
            ->reject(fn (string $metodo) => in_array($metodo, ['HEAD', 'PATCH']))
            ->map(fn (string $metodo) => $metodo.' '.substr($ruta->uri(), 3)))
        ->sort()
        ->values()
        ->all();

    expect($documentado)->toBe($registrado);
});
