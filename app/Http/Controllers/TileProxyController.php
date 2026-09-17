<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TileProxyController extends Controller
{
    public function show(Request $request): Response
    {
        $z = max(0, min(19, (int) $request->query('z', 0)));
        $x = (int) $request->query('x', 0);
        $y = (int) $request->query('y', 0);
        $sub = ['a', 'b', 'c'][($x + $y + $z) % 3];
        $url = "https://{$sub}.tile.openstreetmap.org/{$z}/{$x}/{$y}.png";

        $ctx = stream_context_create(['http' => [
            'header' => "User-Agent: FilmSpec/1.0\r\n",
            'timeout' => 8,
        ]]);
        $data = @file_get_contents($url, false, $ctx);
        if (! $data) {
            return response('', 502);
        }

        return response($data, 200, [
            'Content-Type' => 'image/png',
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
