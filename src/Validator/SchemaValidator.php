<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Validator;

use FilesystemIterator;
use JsonException;
use JsonSchema\Constraints\BaseConstraint;
use JsonSchema\Constraints\Constraint;
use JsonSchema\Constraints\Factory;
use JsonSchema\DraftIdentifiers;
use JsonSchema\SchemaStorage;
use JsonSchema\Validator as JsonRainbowValidator;
use Penyaskito\Dtcg\SpecVersion;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use stdClass;

final class SchemaValidator
{
    private readonly string $rootSchemaId;

    private readonly SchemaStorage $storage;

    public function __construct(
        private readonly SpecVersion $specVersion = SpecVersion::V2025_10,
        ?string $schemaDirectory = null,
    ) {
        $this->storage = new SchemaStorage();
        $this->registerSchemas($schemaDirectory ?? $this->specVersion->schemaDir());
        $this->rootSchemaId = sprintf(
            'https://www.designtokens.org/schemas/%s/format.json',
            $this->specVersion->value,
        );
    }

    /** @return list<Violation> */
    public function validateFile(string $path): array
    {
        $contents = @file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException(sprintf('cannot read file: %s', $path));
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($contents, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException(
                sprintf('invalid JSON in %s: %s', $path, $e->getMessage()),
                0,
                $e,
            );
        }

        return $this->validate($decoded);
    }

    /** @return list<Violation> */
    public function validate(mixed $data): array
    {
        $value = is_array($data) ? BaseConstraint::arrayToObjectRecursive($data) : $data;

        $factory = new Factory($this->storage, null, Constraint::CHECK_MODE_STRICT);
        $factory->setDefaultDialect(DraftIdentifiers::DRAFT_7);
        $validator = new JsonRainbowValidator($factory);

        $refSchema = new stdClass();
        $refSchema->{'$ref'} = $this->rootSchemaId;

        $validator->validate($value, $refSchema);

        /** @var list<array<string, mixed>> $errors */
        $errors = $validator->getErrors();

        $violations = [];
        foreach ($errors as $err) {
            $property = $err['property'] ?? '';
            $message = $err['message'] ?? '';
            $constraint = $err['constraint'] ?? '';
            $violations[] = new Violation(
                source: ViolationSource::Schema,
                path: is_string($property) ? $property : '',
                message: is_string($message) ? $message : '',
                constraint: is_string($constraint) ? $constraint : '',
            );
        }

        return $violations;
    }

    private function registerSchemas(string $dir): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $dir,
                FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO,
            ),
        );

        foreach ($iterator as $file) {
            \assert($file instanceof \SplFileInfo);
            if (!$file->isFile() || $file->getExtension() !== 'json') {
                continue;
            }

            $path = $file->getPathname();
            $contents = @file_get_contents($path);
            if ($contents === false) {
                continue;
            }

            try {
                /** @var mixed $schema */
                $schema = json_decode($contents, false, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                throw new RuntimeException(
                    sprintf('invalid JSON in vendored schema %s: %s', $path, $e->getMessage()),
                    0,
                    $e,
                );
            }

            \assert($schema instanceof stdClass);
            if (!property_exists($schema, '$id')) {
                continue;
            }
            $id = $schema->{'$id'};
            \assert(is_string($id));

            $this->storage->addSchema($id, $schema);
        }
    }
}
