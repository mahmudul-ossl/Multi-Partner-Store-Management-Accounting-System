<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

class IndexController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $endpoints = [];

        foreach (Route::getRoutes() as $route) {
            $name = (string) $route->getName();

            if (! str_starts_with($name, 'api.') || $name === 'api.index') {
                continue;
            }

            $endpoints[] = [
                'method' => implode('|', array_values(array_diff($route->methods(), ['HEAD']))),
                'path' => '/'.$route->uri(),
                'name' => $name,
            ];
        }

        return response()->json([
            'name' => (string) config('app.name'),
            'version' => 'v1',
            'endpoints' => $endpoints,
        ]);
    }
}
