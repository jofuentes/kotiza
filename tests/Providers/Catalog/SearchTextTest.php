<?php
declare(strict_types=1);

namespace Tests\Providers\Catalog;

use App\Providers\Catalog\SearchText;
use App\Providers\Catalog\SyncStatus;
use PHPUnit\Framework\TestCase;

final class SearchTextTest extends TestCase
{
    public function testClean(): void
    {
        $this->assertSame('camara domo 4mp ip67', SearchText::clean('Cámara  Domo/4MP — IP67'));
        $this->assertSame(['nino', 'ano'], SearchText::words('Niño, Año'));
    }

    public function testLooksLikeModel(): void
    {
        $this->assertTrue(SearchText::looksLikeModel('DS-2CD2143G2-I'));
        $this->assertFalse(SearchText::looksLikeModel('camara domo'));
        $this->assertFalse(SearchText::looksLikeModel('switch'));
    }

    public function testSyncIsDue(): void
    {
        $ago = fn (int $s) => date('Y-m-d H:i:s', time() - $s);
        $this->assertTrue(SyncStatus::isDue(null, 12));
        $this->assertTrue(SyncStatus::isDue(['status' => 'queued', 'requested_at' => $ago(1)], 12));
        $this->assertFalse(SyncStatus::isDue(['status' => 'running', 'heartbeat_at' => $ago(30)], 12));
        $this->assertTrue(SyncStatus::isDue(['status' => 'running', 'heartbeat_at' => $ago(3600)], 12), 'abandonada');
        $this->assertFalse(SyncStatus::isDue(['status' => 'done', 'requested_at' => null, 'finished_at' => $ago(3600)], 12));
        $this->assertTrue(SyncStatus::isDue(['status' => 'done', 'requested_at' => null, 'finished_at' => $ago(13 * 3600)], 12));
        $this->assertTrue(SyncStatus::isDue(['status' => 'done', 'requested_at' => $ago(5), 'finished_at' => $ago(60)], 12), 'pedida manualmente');
        $this->assertFalse(SyncStatus::isDue(['status' => 'error', 'requested_at' => null, 'finished_at' => $ago(600)], 12));
        $this->assertTrue(SyncStatus::isDue(['status' => 'error', 'requested_at' => null, 'finished_at' => $ago(1900)], 12));
    }
}
