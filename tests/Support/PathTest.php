<?php

declare(strict_types=1);

namespace Tests\Support;

use Collector369\Support\Path;
use PHPUnit\Framework\TestCase;
use Tests\Concerns\InteractsWithTempDirectories;

final class PathTest extends TestCase
{
    use InteractsWithTempDirectories;

    private string $root;

    protected function setUp(): void
    {
        $this->root = $this->makeTempDirectory('collector369-path');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    public function testJoinBuildsNestedPath(): void
    {
        $path = new Path($this->root);

        self::assertSame($path->root() . '/output/investing', $path->join('output', 'investing'));
    }

    public function testNamedHelpersMatchJoin(): void
    {
        $path = new Path($this->root);

        self::assertSame($path->join('downloads', 'investing'), $path->downloads('investing'));
        self::assertSame($path->join('output'), $path->output());
        self::assertSame($path->join('session', 'investing.json'), $path->session('investing.json'));
        self::assertSame($path->join('logs'), $path->logs());
        self::assertSame($path->join('cache'), $path->cache());
        self::assertSame($path->join('temp'), $path->temp());
    }
}
