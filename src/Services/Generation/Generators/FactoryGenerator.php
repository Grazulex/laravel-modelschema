<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\Services\Generation\Generators;

use Grazulex\LaravelModelschema\Schema\ModelSchema;
use InvalidArgumentException;

/**
 * Generator for Laravel Model Factory Data
 */
class FactoryGenerator extends AbstractGenerator
{
    public function getGeneratorName(): string
    {
        return 'factory';
    }

    public function getAvailableFormats(): array
    {
        return ['json', 'yaml'];
    }

    protected function generateFormat(ModelSchema $schema, string $format, array $options): string
    {
        return match ($format) {
            'json' => $this->generateJson($schema, $options),
            'yaml' => $this->generateYaml($schema, $options),
            default => throw new InvalidArgumentException("Unsupported format: {$format}")
        };
    }

    protected function generateJson(ModelSchema $schema, array $options): string
    {
        // Structure que l'app parent peut insérer dans son JSON
        $factoryData = [
            'name' => "{$schema->name}Factory",
            'namespace' => ($options['factory_namespace'] ?? 'Database\\Factories'),
            'model_class' => $schema->getModelClass(),
            'fields' => $this->getFactoryFields($schema),
            'states' => $this->getFactoryStates($schema),
        ];

        // Retourne la structure prête à être insérée : "factory": { ... }
        return $this->toJsonFormat(['factory' => $factoryData]);
    }

    protected function generateYaml(ModelSchema $schema, array $options): string
    {
        // Structure que l'app parent peut insérer dans son YAML
        $factoryData = [
            'name' => "{$schema->name}Factory",
            'namespace' => ($options['factory_namespace'] ?? 'Database\\Factories'),
            'model_class' => $schema->getModelClass(),
            'fields' => $this->getFactoryFields($schema),
            'states' => $this->getFactoryStates($schema),
        ];

        // Retourne la structure YAML prête à être insérée
        return \Symfony\Component\Yaml\Yaml::dump(['factory' => $factoryData], 4, 2);
    }

    protected function getFactoryFields(ModelSchema $schema): array
    {
        $fields = [];

        foreach ($schema->getFillableFields() as $field) {
            $fields[$field->name] = $this->getFakerMethod($field);
        }

        return $fields;
    }

    protected function getFakerMethod($field): string
    {
        return match ($field->type) {
            'string' => $this->getStringFakerMethod($field),
            'email' => 'fake()->safeEmail()',
            'text' => 'fake()->paragraph()',
            'integer', 'bigInteger' => 'fake()->numberBetween(1, 1000)',
            'decimal', 'float' => 'fake()->randomFloat(2, 0, 1000)',
            'boolean' => 'fake()->boolean()',
            'date' => 'fake()->date()',
            'timestamp' => 'fake()->dateTime()',
            'json' => 'fake()->randomElement([[], [\'key\' => \'value\']])',
            'uuid' => 'fake()->uuid()',
            default => 'fake()->word()'
        };
    }

    protected function getStringFakerMethod($field): string
    {
        // Déterminer le bon faker selon le nom du champ
        return match (true) {
            str_contains(mb_strtolower($field->name), 'name') => 'fake()->name()',
            str_contains(mb_strtolower($field->name), 'title') => 'fake()->sentence(3)',
            str_contains(mb_strtolower($field->name), 'description') => 'fake()->paragraph()',
            str_contains(mb_strtolower($field->name), 'address') => 'fake()->address()',
            str_contains(mb_strtolower($field->name), 'phone') => 'fake()->phoneNumber()',
            str_contains(mb_strtolower($field->name), 'url') => 'fake()->url()',
            str_contains(mb_strtolower($field->name), 'slug') => 'fake()->slug()',
            isset($field->length) && $field->length <= 50 => 'fake()->word()',
            isset($field->length) && $field->length <= 255 => 'fake()->sentence()',
            default => 'fake()->sentence()'
        };
    }

    protected function getFactoryStates(ModelSchema $schema): array
    {
        $states = [];

        // État "inactive" si le modèle a un champ status/active
        foreach ($schema->getAllFields() as $field) {
            if (in_array($field->name, ['status', 'active', 'is_active']) && $field->type === 'boolean') {
                $states['inactive'] = [
                    $field->name => false,
                ];
                break;
            }
        }

        return $states;
    }
}
