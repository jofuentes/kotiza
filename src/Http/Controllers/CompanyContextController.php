<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/** Elegir la empresa con la que se trabaja (usuarios con varias empresas). */
final class CompanyContextController extends Controller
{
    public function select(Request $request): Response
    {
        return $this->view('companies/select', ['companies' => Auth::companies()], 'layouts/guest');
    }

    public function switch(Request $request): Response
    {
        if (!Auth::selectCompany((int) $request->input('company_id'))) {
            Session::flash('error', 'No tienes acceso a esa empresa.');
            return $this->redirect('/empresa/seleccionar');
        }
        return $this->redirect('/');
    }
}
