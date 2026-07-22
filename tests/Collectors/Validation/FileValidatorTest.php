<?php

declare(strict_types=1);

namespace Tests\Collectors\Validation;

use Collector369\Collectors\DTO\CollectedFile;
use Collector369\Collectors\Exceptions\CollectorException;
use Collector369\Collectors\Validation\FileValidator;
use DateTimeImmutable;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\TestCase;
use Tests\Concerns\InteractsWithTempDirectories;

final class FileValidatorTest extends TestCase
{
    use InteractsWithTempDirectories;

    private string $root;
    private FileValidator $validator;

    protected function setUp(): void
    {
        $this->root = $this->makeTempDirectory('collector369-validator');
        $this->validator = new FileValidator();
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    public function testValidatesAWellFormedSpreadsheet(): void
    {
        $path = $this->createSpreadsheet(['Ativo', 'Ouro'], ['Variação', '0,30%']);

        $this->validator->validate($this->collectedFile($path));

        $this->expectNotToPerformAssertions();
    }

    public function testRejectsMissingFile(): void
    {
        $this->expectException(CollectorException::class);

        $this->validator->validate($this->collectedFile($this->root . '/nao-existe.xlsx'));
    }

    public function testRejectsEmptyFile(): void
    {
        $path = $this->root . '/vazio.xlsx';
        file_put_contents($path, '');

        $this->expectException(CollectorException::class);

        $this->validator->validate($this->collectedFile($path));
    }

    public function testRejectsDisallowedExtension(): void
    {
        $path = $this->root . '/arquivo.txt';
        file_put_contents($path, 'conteudo qualquer');

        $this->expectException(CollectorException::class);

        $this->validator->validate($this->collectedFile($path));
    }

    public function testRejectsCorruptedSpreadsheet(): void
    {
        $path = $this->root . '/corrompido.xlsx';
        file_put_contents($path, 'isso nao e um xlsx valido');

        $this->expectException(CollectorException::class);

        $this->validator->validate($this->collectedFile($path));
    }

    public function testRejectsSpreadsheetWithTooFewRows(): void
    {
        $path = $this->createSpreadsheet(['Único cabeçalho']);

        $this->expectException(CollectorException::class);

        $this->validator->validate($this->collectedFile($path));
    }

    private function createSpreadsheet(array ...$rows): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray($rows);

        $path = $this->root . '/planilha-' . uniqid() . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    private function collectedFile(string $path): CollectedFile
    {
        return new CollectedFile(
            path: $path,
            provider: 'investing',
            collectedAt: new DateTimeImmutable(),
            originalFilename: basename($path),
        );
    }
}
