<?php
declare(strict_types=1);

use App\Core\Router;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyContextController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SetupController;

return function (Router $r): void {
    // Asistente de primer arranque (solo disponible mientras no existan usuarios)
    $r->get('/setup', [SetupController::class, 'show']);
    $r->post('/setup', [SetupController::class, 'store']);

    // Autenticación
    $r->group(['guest'], function (Router $r) {
        $r->get('/login', [AuthController::class, 'showLogin']);
        $r->post('/login', [AuthController::class, 'login']);
    });
    $r->post('/logout', [AuthController::class, 'logout'], ['auth.only']);

    // Selección de empresa activa
    $r->group(['auth.only'], function (Router $r) {
        $r->get('/empresa/seleccionar', [CompanyContextController::class, 'select']);
        $r->post('/empresa/seleccionar', [CompanyContextController::class, 'switch']);
    });

    // Área privada
    $r->group(['auth'], function (Router $r) {
        $r->get('/', [DashboardController::class, 'index']);
    });
};
