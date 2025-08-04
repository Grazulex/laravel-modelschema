<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\Services\Generation\Generators;

use Grazulex\LaravelModelschema\Schema\ModelSchema;
use InvalidArgumentException;

/**
 * Generator for Laravel Observer Classes
 */
class ObserverGenerator extends AbstractGenerator
{
    public function getGeneratorName(): string
    {
        return 'observer';
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
        $observerName = "{$schema->name}Observer";
        $observerData = [
            'class_name' => $observerName,
            'namespace' => $options['observer_namespace'] ?? 'App\\Observers',
            'model_class' => $schema->getModelClass(),
            'events' => $this->getObserverEvents($schema, $options),
            'imports' => $this->getImports($schema),
            'methods' => $this->getObserverMethods($schema, $options),
        ];

        return $this->toJsonFormat(['observers' => [$observerName => $observerData]]);
    }

    protected function generateYaml(ModelSchema $schema, array $options): string
    {
        $observerName = "{$schema->name}Observer";
        $observerData = [
            'class_name' => $observerName,
            'namespace' => $options['observer_namespace'] ?? 'App\\Observers',
            'model_class' => $schema->getModelClass(),
            'events' => $this->getObserverEvents($schema, $options),
            'imports' => $this->getImports($schema),
            'methods' => $this->getObserverMethods($schema, $options),
        ];

        return \Symfony\Component\Yaml\Yaml::dump(['observers' => [$observerName => $observerData]], 4, 2);
    }

    protected function getObserverEvents(ModelSchema $schema, array $options): array
    {
        $events = [
            'retrieved' => $options['observe_retrieved'] ?? true,
            'creating' => $options['observe_creating'] ?? true,
            'created' => $options['observe_created'] ?? true,
            'updating' => $options['observe_updating'] ?? true,
            'updated' => $options['observe_updated'] ?? true,
            'saving' => $options['observe_saving'] ?? true,
            'saved' => $options['observe_saved'] ?? true,
            'deleting' => $options['observe_deleting'] ?? true,
            'deleted' => $options['observe_deleted'] ?? true,
        ];

        // Add soft delete events if applicable
        if ($schema->hasSoftDeletes()) {
            $events['restoring'] = $options['observe_restoring'] ?? true;
            $events['restored'] = $options['observe_restored'] ?? true;
            $events['forceDeleted'] = $options['observe_force_deleted'] ?? true;
        }

        return array_filter($events, fn ($enabled) => $enabled);
    }

    protected function getImports(ModelSchema $schema): array
    {
        return [
            $schema->getModelClass(),
            'Illuminate\\Database\\Eloquent\\Model',
        ];
    }

    protected function getObserverMethods(ModelSchema $schema, array $options): array
    {
        $modelName = $schema->name;
        $modelVariable = mb_strtolower($modelName);
        $methods = [];

        $events = $this->getObserverEvents($schema, $options);

        foreach (array_keys($events) as $event) {
            $methods[$event] = [
                'description' => $this->getEventDescription($event, $modelName),
                'parameters' => $this->getEventParameters($event, $modelName, $modelVariable),
                'return_type' => $this->getEventReturnType($event),
                'logic' => $this->getEventLogic($event, $schema, $modelVariable),
            ];
        }

        return $methods;
    }

    protected function getEventDescription(string $event, string $modelName): string
    {
        return match ($event) {
            'retrieved' => "Handle the {$modelName} \"retrieved\" event.",
            'creating' => "Handle the {$modelName} \"creating\" event.",
            'created' => "Handle the {$modelName} \"created\" event.",
            'updating' => "Handle the {$modelName} \"updating\" event.",
            'updated' => "Handle the {$modelName} \"updated\" event.",
            'saving' => "Handle the {$modelName} \"saving\" event.",
            'saved' => "Handle the {$modelName} \"saved\" event.",
            'deleting' => "Handle the {$modelName} \"deleting\" event.",
            'deleted' => "Handle the {$modelName} \"deleted\" event.",
            'restoring' => "Handle the {$modelName} \"restoring\" event.",
            'restored' => "Handle the {$modelName} \"restored\" event.",
            'forceDeleted' => "Handle the {$modelName} \"force deleted\" event.",
            default => "Handle the {$modelName} \"{$event}\" event."
        };
    }

    protected function getEventParameters(string $event, string $modelName, string $modelVariable): array
    {
        return ["{$modelName} \${$modelVariable}"];
    }

    protected function getEventReturnType(string $event): string
    {
        return match ($event) {
            'creating', 'updating', 'saving', 'deleting', 'restoring' => 'bool|void',
            default => 'void'
        };
    }

    protected function getEventLogic(string $event, ModelSchema $schema, string $modelVariable): string
    {
        return match ($event) {
            'retrieved' => '// Log retrieval or update statistics',
            'creating' => "// Validate data, set defaults, generate UUIDs\n        // Return false to cancel creation",
            'created' => '// Send notifications, log creation, trigger workflows',
            'updating' => "// Validate changes, check permissions\n        // Return false to cancel update",
            'updated' => '// Clear caches, send notifications, log changes',
            'saving' => "// Common logic for both create and update\n        // Return false to cancel save",
            'saved' => '// Clear caches, update search indexes',
            'deleting' => "// Check constraints, backup data\n        // Return false to cancel deletion",
            'deleted' => '// Clean up related data, send notifications',
            'restoring' => "// Validate restoration permissions\n        // Return false to cancel restoration",
            'restored' => '// Clear caches, send notifications',
            'forceDeleted' => '// Permanent cleanup, remove files',
            default => "// Handle the {$event} event"
        };
    }
}
