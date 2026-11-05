<?php
declare(strict_types=1);

use App\Core\Router;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyContextController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\UserController;

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
        $r->get('/archivos/{path:.+}', [FileController::class, 'show']);
        $r->get('/perfil', [ProfileController::class, 'edit']);
        $r->post('/perfil', [ProfileController::class, 'update']);

        // Solo administradores
        $r->group(['admin'], function (Router $r) {
            $r->get('/empresa', [CompanyController::class, 'edit']);
            $r->post('/empresa', [CompanyController::class, 'update']);
            $r->get('/empresas/nueva', [CompanyController::class, 'create']);
            $r->post('/empresas', [CompanyController::class, 'store']);

            $r->get('/usuarios', [UserController::class, 'index']);
            $r->get('/usuarios/nuevo', [UserController::class, 'create']);
            $r->post('/usuarios', [UserController::class, 'store']);
            $r->get('/usuarios/{id:\d+}/editar', [UserController::class, 'edit']);
            $r->post('/usuarios/{id:\d+}', [UserController::class, 'update']);
        });
    });
};
