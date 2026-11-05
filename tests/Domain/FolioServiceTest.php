<?php
declare(strict_types=1);

namespace Tests\Domain;

use App\Domain\FolioService;
use PHPUnit\Framework\TestCase;

final class FolioServiceTest extends TestCase
{
    private function company(array $o = []): array
    {
        return $o + ['folio_prefix' => 'COT', 'folio_include_year' => 1, 'folio_reset_yearly' => 1,
            'folio_padding' => 4, 'folio_next' => 158, 'folio_year' => 2026];
    }

    public function testFormat(): void
    {
        $this->assertSame('COT-2026-0001', FolioService::format('COT', true, 2026, 1, 4));
        $this->assertSame('COT-0158', FolioService::format('COT', false, 2026, 158, 4));
        $this->assertSame('2026-012', FolioService::format('', true, 2026, 12, 3));
        $this->assertSame('COT-2026-123456', FolioService::format('COT', true, 2026, 123456, 4));
    }

    public function testSameYearKeepsNumber(): void
    {
        $this->assertSame([158, 2026], FolioService::resolve($this->company(), 2026));
        $this->assertSame('COT-2026-0158', FolioService::preview($this->company(), 2026));
    }

    public function testNewYearResetsWhenEnabled(): void
    {
        $this->assertSame([1, 2027], FolioService::resolve($this->company(), 2027));
        $this->assertSame('COT-2027-0001', FolioService::preview($this->company(), 2027));
    }

    public function testNewYearContinuesWhenResetDisabled(): void
    {
        $this->assertSame([158, 2027], FolioService::resolve($this->company(['folio_reset_yearly' => 0]), 2027));
    }

    public function testMissingYearIsTreatedAsCurrent(): void
    {
        $this->assertSame([158, 2026], FolioService::resolve($this->company(['folio_year' => null]), 2026));
    }

    public function testNormalizePrefix(): void
    {
        $this->assertSame('COT-MX', FolioService::normalizePrefix(' cot-mx! '));
        $this->assertSame('ABC', FolioService::normalizePrefix('-abc-'));
    }
}
