<?php
// Conector Syscom — https://developers.syscom.mx/docs (verificado 2026-11-04)
// Autenticación OAuth2 client_credentials; el token dura 365 días.
return [
    'base_url' => 'https://developers.syscom.mx/api/v1',
    'token_url' => 'https://developers.syscom.mx/api/v1/oauth/token',
    'web_url' => 'https://www.syscom.mx',
    'credentials_url' => 'https://developers.syscom.mx/clients',
    'endpoints' => [
        'search' => '/productos',               // ?busqueda=&categoria=&marca=&stock=&pagina=&limit=&moneda=&inventarios=
        'search_ai' => '/productos/busqueda-ia', // ?q= (lenguaje natural, máx. 60 por página)
        'product' => '/productos/{id}',          // acepta hasta 300 IDs separados por coma
        'categories' => '/categorias',
        'brands' => '/marcas',
        'exchange_rate' => '/tipocambio',        // público
    ],
    'max_ids_per_request' => 300,
    'page_size' => 60,
];
