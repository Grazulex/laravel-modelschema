<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\Services\Generation\Generators;

use Grazulex\LaravelModelschema\Schema\ModelSchema;
use InvalidArgumentException;

/**
 * Generator for Laravel Policy Data
 */
class PolicyGenerator extends AbstractGenerator
{
    public function getGeneratorName(): string
    {
        return 'policy';
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
        $policyName = "{$schema->name}Policy";
        $policyData = [
            'class_name' => $policyName,
            'model' => $schema->name,
            'namespace' => $options['policy_namespace'] ?? 'App\\Policies',
            'model_class' => $schema->getModelClass(),
            'methods' => $this->getPolicyMethods($schema, $options),
            'middleware' => $this->getPolicyMiddleware($options),
            'gates' => $this->getGateDefinitions($schema, $options),
            'authorization_logic' => $this->getAuthorizationLogic($schema, $options),
        ];

        return $this->toJsonFormat(['policies' => [$policyName => $policyData]]);
    }

    protected function generateYaml(ModelSchema $schema, array $options): string
    {
        $policyName = "{$schema->name}Policy";
        $policyData = [
            'class_name' => $policyName,
            'model' => $schema->name,
            'namespace' => $options['policy_namespace'] ?? 'App\\Policies',
            'model_class' => $schema->getModelClass(),
            'methods' => $this->getPolicyMethods($schema, $options),
            'middleware' => $this->getPolicyMiddleware($options),
            'gates' => $this->getGateDefinitions($schema, $options),
            'authorization_logic' => $this->getAuthorizationLogic($schema, $options),
        ];

        return \Symfony\Component\Yaml\Yaml::dump(['policies' => [$policyName => $policyData]], 4, 2);
    }

    protected function getPolicyMethods(ModelSchema $schema, array $options): array
    {
        $modelName = $schema->name;
        $modelVariable = mb_strtolower($modelName);

        $methods = [
            'viewAny' => [
                'description' => "Determine whether the user can view any {$schema->table}.",
                'parameters' => ['User $user'],
                'return_type' => 'bool',
                'logic' => "\$user->hasRole('admin') || \$user->hasPermission('posts.viewAny')",
            ],
            'view' => [
                'description' => "Determine whether the user can view the {$modelName}.",
                'parameters' => ['User $user', "{$modelName} \${$modelVariable}"],
                'return_type' => 'bool',
                'logic' => "\$user->hasPermission('posts.view')",
            ],
            'create' => [
                'description' => "Determine whether the user can create {$schema->table}.",
                'parameters' => ['User $user'],
                'return_type' => 'bool',
                'logic' => "\$user->hasRole(['admin', 'editor']) || \$user->hasPermission('posts.create')",
            ],
            'update' => [
                'description' => "Determine whether the user can update the {$modelName}.",
                'parameters' => ['User $user', "{$modelName} \${$modelVariable}"],
                'return_type' => 'bool',
                'logic' => "\$user->hasRole('admin') || \$user->hasPermission('posts.update')",
            ],
            'delete' => [
                'description' => "Determine whether the user can delete the {$modelName}.",
                'parameters' => ['User $user', "{$modelName} \${$modelVariable}"],
                'return_type' => 'bool',
                'logic' => "\$user->hasRole('admin') || \$user->hasPermission('posts.delete')",
            ],
        ];

        // Soft deletes
        if ($schema->hasSoftDeletes()) {
            $methods['restore'] = [
                'description' => "Determine whether the user can restore the {$modelName}.",
                'parameters' => ['User $user', "{$modelName} \${$modelVariable}"],
                'return_type' => 'bool',
                'logic' => "\$user->can('restore', \${$modelVariable})",
            ];

            $methods['forceDelete'] = [
                'description' => "Determine whether the user can permanently delete the {$modelName}.",
                'parameters' => ['User $user', "{$modelName} \${$modelVariable}"],
                'return_type' => 'bool',
                'logic' => "\$user->can('forceDelete', \${$modelVariable})",
            ];
        }

        // Publish method for publishable models
        if ($this->hasPublishableField($schema)) {
            $methods['publish'] = [
                'description' => "Determine whether the user can publish the {$modelName}.",
                'parameters' => ['User $user', "{$modelName} \${$modelVariable}"],
                'return_type' => 'bool',
                'logic' => "\$user->hasPermission('articles.publish') || \$user->hasRole('admin')",
            ];
        }

        return $methods;
    }

    protected function getPolicyMiddleware(array $options): array
    {
        return $options['middleware'] ?? ['auth', 'verified'];
    }

    protected function getGateDefinitions(ModelSchema $schema, array $options): array
    {
        if (isset($options['include_gates']) && $options['include_gates'] === false) {
            return [];
        }

        $modelNameLower = mb_strtolower($schema->name);

        return [
            "{$modelNameLower}.viewAny" => "Can view any {$schema->table}",
            "{$modelNameLower}.view" => "Can view specific {$schema->name}",
            "{$modelNameLower}.create" => "Can create {$schema->table}",
            "{$modelNameLower}.update" => "Can update {$schema->name}",
            "{$modelNameLower}.delete" => "Can delete {$schema->name}",
        ];
    }

    protected function getAuthorizationLogic(ModelSchema $schema, array $options): array
    {
        $ownershipField = $this->getOwnershipField($schema);

        return [
            'ownership_field' => $ownershipField,
            'ownership_check' => $ownershipField !== null,
            'patterns' => $options['authorization_patterns'] ?? ['ownership', 'role_based', 'permission_based'],
            'supports_soft_deletes' => $schema->hasSoftDeletes(),
            'supports_publishing' => $this->hasPublishableField($schema),
        ];
    }

    protected function getOwnershipField(ModelSchema $schema): ?string
    {
        $ownershipFields = ['user_id', 'owner_id', 'author_id', 'created_by'];

        foreach ($schema->fields as $field) {
            if (in_array($field->name, $ownershipFields)) {
                return $field->name;
            }
        }

        return null;
    }

    protected function hasPublishableField(ModelSchema $schema): bool
    {
        foreach ($schema->fields as $field) {
            if (in_array($field->name, ['published_at', 'status', 'is_published'])) {
                return true;
            }
        }

        return false;
    }
}
