<?php

declare(strict_types=1);

namespace Tests\Support;

use Collector369\Support\ProcessLock;
use PHPUnit\Framework\TestCase;
use Tests\Concerns\InteractsWithTempDirectories;

final class ProcessLockTest extends TestCase
{
    use InteractsWithTempDirectories;

    private string $root;

    protected function setUp(): void
    {
        $this->root = $this->makeTempDirectory('collector369-processlock');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    public function testAcquiresLockWhenFileDoesNotYetExist(): void
    {
        $lock = new ProcessLock($this->root . '/cache/cycle-run.lock');

        self::assertTrue($lock->acquire());
        self::assertFileExists($this->root . '/cache/cycle-run.lock');

        $lock->release();
    }

    public function testSecondLockFailsToAcquireWhileFirstIsHeld(): void
    {
        $lockPath = $this->root . '/cache/cycle-run.lock';

        $first = new ProcessLock($lockPath);
        $second = new ProcessLock($lockPath);

        self::assertTrue($first->acquire());
        self::assertFalse($second->acquire(), 'um segundo ciclo não deve conseguir travar enquanto o primeiro está rodando');

        $first->release();
    }

    public function testLockCanBeAcquiredAgainAfterRelease(): void
    {
        $lockPath = $this->root . '/cache/cycle-run.lock';

        $first = new ProcessLock($lockPath);
        self::assertTrue($first->acquire());
        $first->release();

        $second = new ProcessLock($lockPath);
        self::assertTrue($second->acquire(), 'após liberado, o lock deve poder ser adquirido novamente');

        $second->release();
    }
}
