<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;

/** Sirve logos, membretes y firmas solo a usuarios de la empresa dueña del archivo. */
final class FileController extends Controller
{
    private const TYPES = [
        'png' => 'image/png', 'jpg' => 'image/jpeg', 'svg' => 'image/svg+xml', 'pdf' => 'application/pdf',
    ];

    public function show(Request $request): Response
    {
        $path = (string) ($request->params['path'] ?? '');
        if (!preg_match('#^(logos|letterheads|signatures)/(\d+)/[a-f0-9]{24}\.(png|jpg|svg|pdf)$#', $path, $m)) {
            throw new HttpException(404);
        }
        $companyIds = array_map(fn ($c) => (int) $c['id'], Auth::companies());
        if (!in_array((int) $m[2], $companyIds, true)) {
            throw new HttpException(404);
        }
        $file = storage_path($path);
        if (!is_file($file)) {
            throw new HttpException(404);
        }
        return new Response((string) file_get_contents($file), 200, [
            'Content-Type' => self::TYPES[$m[3]],
            'Content-Length' => (string) filesize($file),
            'Cache-Control' => 'private, max-age=86400',
            // Un SVG abierto directamente no puede ejecutar nada
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; img-src data:; sandbox",
            'Content-Disposition' => 'inline; filename="' . basename($path) . '"',
        ]);
    }
}
