<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\FieldTypes;

/**
 * Text field type implementation
 */
final class TextFieldType extends AbstractFieldType
{
    protected array $specificAttributes = [
        'size', // text, mediumText, longText
    ];

    public function getType(): string
    {
        return 'text';
    }

    public function getAliases(): array
    {
        return ['longtext', 'mediumtext'];
    }

    public function getMigrationMethod(): string
    {
        return match ($this->getSize()) {
            'medium' => 'mediumText',
            'long' => 'longText',
            default => 'text',
        };
    }

    public function getCastType(): string
    {
        return 'string';
    }

    public function getValidationRules(array $config = []): array
    {
        $rules = parent::getValidationRules($config);

        $rules[] = 'string';

        return $rules;
    }

    public function getMigrationParameters(array $config): array
    {
        return []; // Text types don't need parameters
    }

    public function validate(array $config): array
    {
        $errors = parent::validate($config);

        // Validate size
        if (isset($config['size']) && ! in_array($config['size'], ['text', 'medium', 'long'])) {
            $errors[] = 'Text size must be one of: text, medium, long';
        }

        return $errors;
    }

    private function getSize(): string
    {
        return 'text'; // Default size
    }
}
