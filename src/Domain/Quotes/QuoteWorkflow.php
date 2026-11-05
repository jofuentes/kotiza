<?php
declare(strict_types=1);

namespace App\Domain\Quotes;

/**
 * Flujo de estados de una cotización:
 *
 *   draft ─submit→ review ─approve→ approved ─send→ sent ─accept→ accepted
 *     ▲              │                  │               └─reject→ rejected
 *     └──return──────┘ (admin, con nota) │       sent/approved ─(vence)→ expired
 *     └──withdraw (quien la envió a revisión)
 *     └──reopen (admin: approved → draft)
 *
 * Si la empresa no exige aprobación: draft ─send→ sent (salvo descuentos pendientes).
 * Un administrador puede aprobar directamente desde draft.
 */
final class QuoteWorkflow
{
    public const LABELS = [
        'draft' => 'Borrador', 'review' => 'En revisión', 'approved' => 'Aprobada', 'sent' => 'Enviada',
        'accepted' => 'Aceptada', 'rejected' => 'Rechazada', 'expired' => 'Vencida',
    ];

    /** acción => [desde => hacia] */
    private const TRANSITIONS = [
        'submit' => ['draft' => 'review'],
        'withdraw' => ['review' => 'draft'],
        'return' => ['review' => 'draft'],
        'approve' => ['review' => 'approved', 'draft' => 'approved'],
        'reopen' => ['approved' => 'draft'],
        'send' => ['approved' => 'sent', 'draft' => 'sent'],
        'accept' => ['sent' => 'accepted', 'expired' => 'accepted'],
        'reject' => ['sent' => 'rejected', 'expired' => 'rejected'],
    ];

    public const ACTION_LABELS = [
        'submit' => 'Solicitar aprobación', 'withdraw' => 'Retirar de revisión', 'return' => 'Regresar a borrador',
        'approve' => 'Aprobar', 'reopen' => 'Reabrir para editar', 'send' => 'Marcar como enviada',
        'accept' => 'Aceptada por el cliente', 'reject' => 'Rechazada por el cliente',
    ];

    /** Acciones que piden un comentario */
    public const NEEDS_NOTE = ['return', 'reject'];

    /**
     * @param array{is_admin: bool, can_edit_access: bool, require_approval: bool, discount_pending: bool, has_items: bool} $ctx
     *        can_edit_access: es creador, colaborador con edición o administrador
     * @return ?string motivo si no se permite; null si se permite
     */
    public static function check(string $status, string $action, array $ctx): ?string
    {
        $to = self::TRANSITIONS[$action][$status] ?? null;
        if ($to === null) {
            return 'Esta acción no aplica en el estado actual.';
        }
        if (!$ctx['can_edit_access']) {
            return 'No tienes permiso para cambiar el estado de esta cotización.';
        }
        $admin = $ctx['is_admin'];
        return match ($action) {
            'submit' => !$ctx['require_approval'] ? 'La empresa no exige aprobación: puedes enviarla directamente.'
                : (!$ctx['has_items'] ? 'Agrega al menos una partida antes de solicitar aprobación.' : null),
            'return', 'reopen' => $admin ? null : 'Solo un administrador puede hacerlo.',
            'approve' => !$admin ? 'Solo un administrador puede aprobar cotizaciones.'
                : (!$ctx['has_items'] ? 'La cotización no tiene partidas.' : null),
            'send' => match (true) {
                !$ctx['has_items'] => 'La cotización no tiene partidas.',
                $status === 'draft' && $ctx['require_approval'] => 'Primero debe aprobarla un administrador.',
                $status === 'draft' && $ctx['discount_pending'] => 'Hay descuentos pendientes de aprobación de un administrador.',
                default => null,
            },
            default => null,
        };
    }

    public static function target(string $status, string $action): ?string
    {
        return self::TRANSITIONS[$action][$status] ?? null;
    }

    /** @return string[] acciones permitidas ahora */
    public static function available(string $status, array $ctx): array
    {
        $out = [];
        foreach (array_keys(self::TRANSITIONS) as $action) {
            if (self::check($status, $action, $ctx) === null) {
                $out[] = $action;
            }
        }
        return $out;
    }

    /** ¿Se puede editar el contenido en este estado? */
    public static function editableStatus(string $status, bool $isAdmin): bool
    {
        return $status === 'draft' || ($status === 'review' && $isAdmin);
    }

    /** ¿El PDF debe llevar marca de agua? */
    public static function watermark(string $status, bool $requireApproval): ?string
    {
        return match (true) {
            $status === 'expired' => 'VENCIDA',
            $requireApproval && in_array($status, ['draft', 'review'], true) => 'BORRADOR',
            default => null,
        };
    }
}
