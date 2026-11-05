<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

final class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $cid = Auth::companyId();
        $mine = Auth::isAdmin() ? '' : ' AND user_id = ' . (int) Auth::id();
        $stats = Database::fetch(
            "SELECT COUNT(*) total, SUM(status = 'draft') drafts,
                    SUM(status = 'accepted' AND updated_at >= DATE_FORMAT(NOW(), '%Y-%m-01')) accepted_month
               FROM quotes WHERE company_id = ?{$mine}", [$cid]
        );
        $recent = Database::fetchAll(
            'SELECT q.id, q.folio, q.version, q.status, q.title, q.currency, q.total, q.updated_at, c.name client_name,
                    qs.permission AS shared_permission
               FROM quotes q LEFT JOIN clients c ON c.id = q.client_id
               LEFT JOIN quote_shares qs ON qs.quote_id = q.id AND qs.user_id = ?
              WHERE q.company_id = ?' . (Auth::isAdmin() ? '' : ' AND (q.user_id = ? OR qs.user_id IS NOT NULL)')
            . ' ORDER BY q.updated_at DESC LIMIT 8',
            Auth::isAdmin() ? [Auth::id(), $cid] : [Auth::id(), $cid, Auth::id()]
        );
        return $this->view('dashboard/index', [
            'stats' => [
                ['Cotizaciones', (int) $stats['total'], '/cotizaciones'],
                ['Borradores', (int) $stats['drafts'], '/cotizaciones?status=draft'],
                ['Aceptadas este mes', (int) $stats['accepted_month'], '/cotizaciones?status=accepted'],
                ['Clientes', (int) Database::value('SELECT COUNT(*) FROM clients WHERE company_id = ?', [$cid]), '/clientes'],
            ],
            'recent' => $recent,
        ]);
    }
}
