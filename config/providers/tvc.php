<?php
// Conector TVC — documentación: https://api.tvc.mx/doc-site/index.html (revisada 2026-11-04)
//
// Verificado en la documentación:
//   - Token Bearer de 1 año, generado en tvc.mx → Mi Perfil → API Clientes (requiere aprobación de TVC)
//   - GET /products no busca por texto: se sincroniza el catálogo completo a una tabla local
//   - Límite: 200 peticiones por minuto
//   - Imágenes: rutas relativas (/media/...) servidas desde https://cdn.tvc.mx (comprobado)
//
// Pendiente de confirmar con un token real (marcado con TODO):
return [
    'base_url' => 'https://api.tvc.mx',
    'media_base_url' => 'https://cdn.tvc.mx',
    'credentials_url' => 'https://tvc.mx',
    'endpoints' => [
        'products' => '/products',              // ?page=&perPage=(máx 100)&withPrice=true&withInventory=simple|detailed&withMedia=true&tvcIds[]=
        'product' => '/products/{id}',          // ?byModel=CLAVE para buscar por clave TVC
        'brands' => '/brands',
        'categories' => '/categories',
        'exchange_rate' => '/exchange-rates',
    ],
    'per_page' => 100,                          // máximo permitido por TVC
    'max_ids_per_request' => 100,
    // 200 req/min → una página cada 450 ms deja margen para búsquedas y precios en vivo
    'sync_delay_ms' => 450,
    // TODO(TVC): confirmar la URL pública de la ficha de producto en el sitio de TVC.
    //            Usar {tvc_model} o {tvc_id}. Mientras sea null no se muestra enlace.
    'product_url_template' => null,
];
