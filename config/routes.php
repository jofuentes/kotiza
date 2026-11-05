<?php
declare(strict_types=1);

use App\Core\Router;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CompanyContextController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProviderController;
use App\Http\Controllers\QuoteController;
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

        // Clientes
        $r->get('/clientes', [ClientController::class, 'index']);
        $r->get('/clientes/buscar', [ClientController::class, 'search']);
        $r->get('/clientes/nuevo', [ClientController::class, 'create']);
        $r->post('/clientes', [ClientController::class, 'store']);
        $r->get('/clientes/{id:\d+}/editar', [ClientController::class, 'edit']);
        $r->post('/clientes/{id:\d+}', [ClientController::class, 'update']);
        $r->post('/clientes/{id:\d+}/eliminar', [ClientController::class, 'destroy']);

        // Cotizaciones
        $r->get('/cotizaciones', [QuoteController::class, 'index']);
        $r->get('/cotizaciones/nueva', [QuoteController::class, 'create']);
        $r->post('/cotizaciones', [QuoteController::class, 'store']);
        $r->get('/cotizaciones/{id:\d+}', [QuoteController::class, 'edit']);
        $r->post('/cotizaciones/{id:\d+}', [QuoteController::class, 'save']);
        $r->post('/cotizaciones/{id:\d+}/productos', [QuoteController::class, 'addProduct']);
        $r->get('/cotizaciones/{id:\d+}/tipo-cambio', [QuoteController::class, 'exchangeRate']);
        $r->post('/cotizaciones/{id:\d+}/eliminar', [QuoteController::class, 'destroy']);
        $r->post('/cotizaciones/{id:\d+}/aprobar', [QuoteController::class, 'approve'], ['admin']);
        $r->get('/cotizaciones/{id:\d+}/pdf', [QuoteController::class, 'pdf']);
        $r->post('/cotizaciones/{id:\d+}/compartir', [QuoteController::class, 'share']);

        // Catálogo de proveedores
        $r->get('/productos', [CatalogController::class, 'index']);
        $r->get('/productos/buscar', [CatalogController::class, 'search']);
        $r->get('/productos/{code:[a-z0-9_]+}/{id:[A-Za-z0-9_.-]+}', [CatalogController::class, 'show']);

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

            $r->get('/precios', [PricingController::class, 'index']);
            $r->post('/precios', [PricingController::class, 'updateGlobal']);
            $r->post('/precios/reglas', [PricingController::class, 'storeRule']);
            $r->post('/precios/reglas/{id:\d+}', [PricingController::class, 'updateRule']);
            $r->post('/precios/reglas/{id:\d+}/eliminar', [PricingController::class, 'destroyRule']);
            $r->get('/precios/simular', [PricingController::class, 'preview']);

            $r->get('/proveedores', [ProviderController::class, 'index']);
            $r->post('/proveedores/cache', [ProviderController::class, 'cache']);
            $r->get('/proveedores/{code:[a-z0-9_]+}', [ProviderController::class, 'edit']);
            $r->post('/proveedores/{code:[a-z0-9_]+}', [ProviderController::class, 'update']);
            $r->post('/proveedores/{code:[a-z0-9_]+}/probar', [ProviderController::class, 'test']);
            $r->get('/proveedores/{code:[a-z0-9_]+}/log', [ProviderController::class, 'logs']);
            $r->post('/proveedores/{code:[a-z0-9_]+}/sincronizar', [ProviderController::class, 'sync']);
            $r->get('/proveedores/{code:[a-z0-9_]+}/sincronizacion', [ProviderController::class, 'syncStatus']);
        });
    });
};
