<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tests\Integration\Validator;

use PHPUnit\Framework\TestCase;
use Penyaskito\Dtcg\Validator\SchemaValidator;
use Penyaskito\Dtcg\Validator\Violation;
use Penyaskito\Dtcg\Validator\ViolationSource;

final class SchemaValidatorTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../../fixtures';

    public function testValidDimensionFixtureProducesNoViolations(): void
    {
        $validator = new SchemaValidator();
        $violations = $validator->validateFile(self::FIXTURES . '/valid/minimal-dimension.tokens.json');

        self::assertSame([], $violations, self::describe($violations));
    }

    public function testInvalidDimensionUnitIsRejected(): void
    {
        $validator = new SchemaValidator();
        $violations = $validator->validateFile(self::FIXTURES . '/invalid/bad-dimension-unit.tokens.json');

        self::assertNotSame([], $violations);
        foreach ($violations as $v) {
            self::assertSame(ViolationSource::Schema, $v->source);
        }
        $messages = array_map(static fn (Violation $v): string => $v->path . ': ' . $v->message, $violations);
        $joined = implode("\n", $messages);
        self::assertStringContainsString('unit', $joined);
    }

    public function testUnknownTypeIsRejected(): void
    {
        $validator = new SchemaValidator();
        $violations = $validator->validateFile(self::FIXTURES . '/invalid/unknown-type.tokens.json');

        self::assertNotSame([], $violations);
    }

    public function testBadTokenNameIsRejected(): void
    {
        $validator = new SchemaValidator();
        $violations = $validator->validateFile(self::FIXTURES . '/invalid/bad-token-name.tokens.json');

        self::assertNotSame([], $violations, 'names containing "." should be rejected by patternProperties');
        foreach ($violations as $v) {
            self::assertSame(ViolationSource::Schema, $v->source);
        }
    }

    public function testExtraUnknownDollarPropertyIsRejected(): void
    {
        $validator = new SchemaValidator();
        $violations = $validator->validateFile(self::FIXTURES . '/invalid/extra-property.tokens.json');

        self::assertNotSame([], $violations, 'token-level additionalProperties: false should catch $nonsense');
    }

    public function testMultiViolationDocumentAggregatesErrors(): void
    {
        $validator = new SchemaValidator();
        $violations = $validator->validateFile(self::FIXTURES . '/invalid/multi-violation.tokens.json');

        self::assertGreaterThanOrEqual(2, count($violations), 'expected at least two independent schema violations');
    }

    public function testValidateAcceptsDecodedArrayDirectly(): void
    {
        $validator = new SchemaValidator();
        $decoded = json_decode(
            (string) file_get_contents(self::FIXTURES . '/valid/minimal-dimension.tokens.json'),
            true,
        );

        self::assertIsArray($decoded);
        $violations = $validator->validate($decoded);

        self::assertSame([], $violations);
    }

    public function testValidateAcceptsDecodedObjectDirectly(): void
    {
        $validator = new SchemaValidator();
        $decoded = json_decode(
            (string) file_get_contents(self::FIXTURES . '/valid/minimal-dimension.tokens.json'),
            false,
        );

        $violations = $validator->validate($decoded);

        self::assertSame([], $violations);
    }

    public function testValidateFileThrowsOnUnreadablePath(): void
    {
        $validator = new SchemaValidator();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('cannot read file');

        $validator->validateFile('/no/such/path.json');
    }

    public function testValidateFileThrowsOnInvalidJson(): void
    {
        $validator = new SchemaValidator();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('invalid JSON');

        $validator->validateFile(self::FIXTURES . '/invalid/not-json.tokens.json');
    }

    public function testMalformedSchemaFileInCustomDirectoryIsReported(): void
    {
        $dir = sys_get_temp_dir() . '/dtcg-schemadir-' . uniqid();
        mkdir($dir, 0o700, true);
        file_put_contents($dir . '/broken.json', '{ not json');
        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('invalid JSON in vendored schema');
            new SchemaValidator(schemaDirectory: $dir);
        } finally {
            @unlink($dir . '/broken.json');
            @rmdir($dir);
        }
    }

    public function testSchemaFileWithoutIdIsSilentlySkipped(): void
    {
        // A valid JSON object with no $id is legal (it just can't be registered by URI),
        // so registerSchemas() must skip it rather than fail.
        $this->expectNotToPerformAssertions();
        $dir = sys_get_temp_dir() . '/dtcg-schemadir-' . uniqid();
        mkdir($dir, 0o700, true);
        file_put_contents($dir . '/noid.json', '{"type": "object"}');
        try {
            new SchemaValidator(schemaDirectory: $dir);
        } finally {
            @unlink($dir . '/noid.json');
            @rmdir($dir);
        }
    }

    public function testNonJsonFilesInSchemaDirectoryAreSkipped(): void
    {
        $this->expectNotToPerformAssertions();
        $dir = sys_get_temp_dir() . '/dtcg-schemadir-' . uniqid();
        mkdir($dir, 0o700, true);
        file_put_contents($dir . '/readme.txt', 'not a schema');
        try {
            new SchemaValidator(schemaDirectory: $dir);
        } finally {
            @unlink($dir . '/readme.txt');
            @rmdir($dir);
        }
    }

    /** @param list<Violation> $violations */
    private static function describe(array $violations): string
    {
        if ($violations === []) {
            return '';
        }

        return "unexpected violations:\n" . implode("\n", array_map(
            static fn (Violation $v): string => sprintf(' - [%s] %s: %s', $v->constraint, $v->path, $v->message),
            $violations,
        ));
    }
}
