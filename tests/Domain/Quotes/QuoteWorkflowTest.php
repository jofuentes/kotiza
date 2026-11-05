<?php
declare(strict_types=1);

namespace Tests\Domain\Quotes;

use App\Domain\Quotes\QuoteWorkflow as W;
use PHPUnit\Framework\TestCase;

final class QuoteWorkflowTest extends TestCase
{
    private function ctx(array $o = []): array
    {
        return $o + ['is_admin' => false, 'can_edit_access' => true, 'require_approval' => true, 'discount_pending' => false, 'has_items' => true];
    }

    public function testSellerWithApprovalRequired(): void
    {
        $this->assertSame(['submit'], W::available('draft', $this->ctx()));
        $this->assertSame(['withdraw'], W::available('review', $this->ctx()));
        $this->assertSame(['send'], W::available('approved', $this->ctx()));
        $this->assertSame(['accept', 'reject'], W::available('sent', $this->ctx()));
        $this->assertSame('Primero debe aprobarla un administrador.', W::check('draft', 'send', $this->ctx()));
        $this->assertSame('Solo un administrador puede aprobar cotizaciones.', W::check('review', 'approve', $this->ctx()));
    }

    public function testAdmin(): void
    {
        $admin = $this->ctx(['is_admin' => true]);
        $this->assertSame(['submit', 'approve'], W::available('draft', $admin));
        $this->assertSame(['withdraw', 'return', 'approve'], W::available('review', $admin));
        $this->assertSame(['reopen', 'send'], W::available('approved', $admin));
    }

    public function testWithoutApprovalRequired(): void
    {
        $c = $this->ctx(['require_approval' => false]);
        $this->assertSame(['send'], W::available('draft', $c));
        $this->assertNotNull(W::check('draft', 'submit', $c));
        $this->assertSame('Hay descuentos pendientes de aprobación de un administrador.',
            W::check('draft', 'send', array_merge($c, ['discount_pending' => true])));
    }

    public function testReadOnlyCollaboratorCannotChangeStatus(): void
    {
        $this->assertSame([], W::available('draft', $this->ctx(['can_edit_access' => false])));
    }

    public function testEmptyQuoteCannotBeSubmittedOrSent(): void
    {
        $this->assertSame([], W::available('draft', $this->ctx(['has_items' => false])));
    }

    public function testExpiredCanStillBeAcceptedOrRejected(): void
    {
        $this->assertSame(['accept', 'reject'], W::available('expired', $this->ctx()));
        $this->assertSame('accepted', W::target('expired', 'accept'));
    }

    public function testEditableAndWatermark(): void
    {
        $this->assertTrue(W::editableStatus('draft', false));
        $this->assertFalse(W::editableStatus('review', false));
        $this->assertTrue(W::editableStatus('review', true));
        $this->assertFalse(W::editableStatus('approved', true));
        $this->assertSame('BORRADOR', W::watermark('review', true));
        $this->assertNull(W::watermark('draft', false));
        $this->assertNull(W::watermark('approved', true));
        $this->assertSame('VENCIDA', W::watermark('expired', false));
    }
}
