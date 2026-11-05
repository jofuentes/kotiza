<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Response;
use App\Core\View;

abstract class Controller
{
    protected function view(string $template, array $data = [], ?string $layout = 'layouts/app'): Response
    {
        if ($layout === 'layouts/app') {
            $data += [
                'authUser'    => Auth::user(),
                'company'     => Auth::company(),
                'companies'   => Auth::companies(),
                'isAdmin'     => Auth::isAdmin(),
            ];
        }
        return new Response(View::render($template, $data, $layout));
    }

    protected function redirect(string $to): Response
    {
        return Response::redirect($to);
    }
}
