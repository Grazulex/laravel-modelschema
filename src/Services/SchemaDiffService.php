<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\Services;

use Exception;
use Grazulex\LaravelModelschema\Schema\Field;
use Grazulex\LaravelModelschema\Schema\ModelSchema;
use Grazulex\LaravelModelschema\Schema\Relationship;

/**
 * Service for comparing schemas and generating diff reports
 */
class SchemaDiffService
{
    private LoggingService $logger;

    public function __construct(?LoggingService $logger = null)
    {
        $this->logger = $logger ?? new LoggingService();
    }

    /**
     * Compare two schemas and return a comprehensive diff
     */
    public function compareSchemas(ModelSchema $oldSchema, ModelSchema $newSchema): array
    {
        $this->logger->logOperationStart('compareSchemas', [
            'old_schema' => $oldSchema->name,
            'new_schema' => $newSchema->name,
            'old_fields_count' => count($oldSchema->getAllFields()),
            'new_fields_count' => count($newSchema->getAllFields()),
        ]);

        $diff = [
            'summary' => $this->generateSummary($oldSchema, $newSchema),
            'schema_changes' => $this->compareSchemaMetadata($oldSchema, $newSchema),
            'field_changes' => $this->compareFields($oldSchema, $newSchema),
            'relationship_changes' => $this->compareRelationships($oldSchema, $newSchema),
            'migration_impact' => $this->analyzeMigrationImpact($oldSchema, $newSchema),
            'validation_impact' => $this->analyzeValidationImpact($oldSchema, $newSchema),
            'breaking_changes' => $this->identifyBreakingChanges($oldSchema, $newSchema),
        ];

        $this->logger->logOperationEnd('compareSchemas', [
            'total_changes' => $this->countTotalChanges($diff),
            'breaking_changes' => count($diff['breaking_changes']),
            'field_changes' => count($diff['field_changes']['added']) + count($diff['field_changes']['removed']) + count($diff['field_changes']['modified']),
        ]);

        return $diff;
    }

    /**
     * Generate a human-readable diff report
     */
    public function generateDiffReport(array $diff): string
    {
        $report = [];
        $report[] = '# Schema Diff Report';
        $report[] = '';
        $report[] = '## Summary';
        $report[] = "- Schema: {$diff['summary']['schema_name']}";
        $report[] = "- Table: {$diff['summary']['table_name']}";
        $report[] = "- Compatibility: {$diff['summary']['compatibility']}";
        $report[] = '';

        // Field changes
        if (! empty($diff['field_changes']['added'])) {
            $report[] = '### Fields Added ('.count($diff['field_changes']['added']).')';
            foreach ($diff['field_changes']['added'] as $fieldName => $field) {
                $report[] = "- **{$fieldName}**: {$field['type']} ".($field['nullable'] ? '(nullable)' : '(required)');
            }
            $report[] = '';
        }

        if (! empty($diff['field_changes']['removed'])) {
            $report[] = '### Fields Removed ('.count($diff['field_changes']['removed']).')';
            foreach ($diff['field_changes']['removed'] as $fieldName => $field) {
                $report[] = "- **{$fieldName}**: {$field['type']} ⚠️ Data loss risk";
            }
            $report[] = '';
        }

        if (! empty($diff['field_changes']['modified'])) {
            $report[] = '### Fields Modified ('.count($diff['field_changes']['modified']).')';
            foreach ($diff['field_changes']['modified'] as $fieldName => $changes) {
                $report[] = "- **{$fieldName}**:";
                foreach ($changes as $changeType => $changeData) {
                    $breaking = isset($changeData['breaking']) && $changeData['breaking'] ? ' ⚠️ Breaking' : '';
                    $report[] = "  - {$changeType}: {$changeData['old']} → {$changeData['new']}{$breaking}";
                }
            }
            $report[] = '';
        }

        // Breaking changes
        if (! empty($diff['breaking_changes'])) {
            $report[] = '### ⚠️ Breaking Changes ('.count($diff['breaking_changes']).')';
            foreach ($diff['breaking_changes'] as $change) {
                $report[] = "- **{$change['type']}**: {$change['description']} (Impact: {$change['impact']})";
            }
            $report[] = '';
        }

        // Migration impact
        if ($diff['migration_impact']['requires_migration']) {
            $report[] = '### Migration Required';
            $report[] = "- Data loss risk: {$diff['migration_impact']['data_loss_risk']}";
            $report[] = '- Operations: '.count($diff['migration_impact']['migration_operations']);
            $report[] = '';
        }

        return implode("\n", $report);
    }

    /**
     * Generate a summary of changes
     */
    private function generateSummary(ModelSchema $oldSchema, ModelSchema $newSchema): array
    {
        $oldFields = $oldSchema->getAllFields();
        $newFields = $newSchema->getAllFields();
        $oldRelations = $oldSchema->relationships;
        $newRelations = $newSchema->relationships;

        return [
            'schema_name' => $newSchema->name,
            'table_name' => $newSchema->table,
            'old_table_name' => $oldSchema->table,
            'table_renamed' => $oldSchema->table !== $newSchema->table,
            'fields' => [
                'old_count' => count($oldFields),
                'new_count' => count($newFields),
                'added' => count(array_diff_key($newFields, $oldFields)),
                'removed' => count(array_diff_key($oldFields, $newFields)),
                'modified' => $this->countModifiedFields($oldFields, $newFields),
            ],
            'relationships' => [
                'old_count' => count($oldRelations),
                'new_count' => count($newRelations),
                'added' => count(array_diff_key($newRelations, $oldRelations)),
                'removed' => count(array_diff_key($oldRelations, $newRelations)),
                'modified' => $this->countModifiedRelationships($oldRelations, $newRelations),
            ],
            'compatibility' => $this->determineCompatibility($oldSchema, $newSchema),
        ];
    }

    /**
     * Compare schema metadata (name, table, options)
     */
    private function compareSchemaMetadata(ModelSchema $oldSchema, ModelSchema $newSchema): array
    {
        $changes = [];

        if ($oldSchema->name !== $newSchema->name) {
            $changes['model_name'] = [
                'old' => $oldSchema->name,
                'new' => $newSchema->name,
                'type' => 'renamed',
            ];
        }

        if ($oldSchema->table !== $newSchema->table) {
            $changes['table_name'] = [
                'old' => $oldSchema->table,
                'new' => $newSchema->table,
                'type' => 'renamed',
            ];
        }

        $optionsDiff = $this->compareArrays($oldSchema->options, $newSchema->options);
        if ($optionsDiff !== []) {
            $changes['options'] = $optionsDiff;
        }

        $metadataDiff = $this->compareArrays($oldSchema->metadata, $newSchema->metadata);
        if ($metadataDiff !== []) {
            $changes['metadata'] = $metadataDiff;
        }

        return $changes;
    }

    /**
     * Compare fields between schemas
     */
    private function compareFields(ModelSchema $oldSchema, ModelSchema $newSchema): array
    {
        $oldFields = $oldSchema->getAllFields();
        $newFields = $newSchema->getAllFields();

        $added = array_diff_key($newFields, $oldFields);
        $removed = array_diff_key($oldFields, $newFields);
        $common = array_intersect_key($oldFields, $newFields);

        $modified = [];
        foreach ($common as $fieldName => $oldField) {
            $newField = $newFields[$fieldName];
            $fieldDiff = $this->compareField($oldField, $newField);
            if ($fieldDiff !== []) {
                $modified[$fieldName] = $fieldDiff;
            }
        }

        return [
            'added' => $this->fieldsToArray($added),
            'removed' => $this->fieldsToArray($removed),
            'modified' => $modified,
        ];
    }

    /**
     * Compare a single field
     */
    private function compareField(Field $oldField, Field $newField): array
    {
        $changes = [];

        // Type change
        if ($oldField->type !== $newField->type) {
            $changes['type'] = [
                'old' => $oldField->type,
                'new' => $newField->type,
                'breaking' => $this->isTypeChangeBreaking($oldField->type, $newField->type),
            ];
        }

        // Nullable change
        if ($oldField->nullable !== $newField->nullable) {
            $changes['nullable'] = [
                'old' => $oldField->nullable,
                'new' => $newField->nullable,
                'breaking' => $oldField->nullable && ! $newField->nullable, // Making nullable field required is breaking
            ];
        }

        // Default value change
        if ($oldField->default !== $newField->default) {
            $changes['default'] = [
                'old' => $oldField->default,
                'new' => $newField->default,
                'breaking' => false,
            ];
        }

        // Unique constraint change
        if ($oldField->unique !== $newField->unique) {
            $changes['unique'] = [
                'old' => $oldField->unique,
                'new' => $newField->unique,
                'breaking' => ! $oldField->unique && $newField->unique, // Adding unique constraint is potentially breaking
            ];
        }

        // Index change
        if ($oldField->index !== $newField->index) {
            $changes['index'] = [
                'old' => $oldField->index,
                'new' => $newField->index,
                'breaking' => false,
            ];
        }

        // Length change
        if ($oldField->length !== $newField->length) {
            $changes['length'] = [
                'old' => $oldField->length,
                'new' => $newField->length,
                'breaking' => $oldField->length !== null && $newField->length !== null && $newField->length < $oldField->length,
            ];
        }

        // Precision/Scale changes
        if ($oldField->precision !== $newField->precision) {
            $changes['precision'] = [
                'old' => $oldField->precision,
                'new' => $newField->precision,
                'breaking' => $oldField->precision !== null && $newField->precision !== null && $newField->precision < $oldField->precision,
            ];
        }

        if ($oldField->scale !== $newField->scale) {
            $changes['scale'] = [
                'old' => $oldField->scale,
                'new' => $newField->scale,
                'breaking' => $oldField->scale !== null && $newField->scale !== null && $newField->scale < $oldField->scale,
            ];
        }

        // Attributes changes
        $attributesDiff = $this->compareArrays($oldField->attributes, $newField->attributes);
        if ($attributesDiff !== []) {
            $changes['attributes'] = $attributesDiff;
        }

        // Rules changes
        $rulesDiff = $this->compareArrays($oldField->rules, $newField->rules);
        if ($rulesDiff !== []) {
            $changes['rules'] = $rulesDiff;
        }

        return $changes;
    }

    /**
     * Compare relationships between schemas
     */
    private function compareRelationships(ModelSchema $oldSchema, ModelSchema $newSchema): array
    {
        $oldRelations = $oldSchema->relationships;
        $newRelations = $newSchema->relationships;

        $added = array_diff_key($newRelations, $oldRelations);
        $removed = array_diff_key($oldRelations, $newRelations);
        $common = array_intersect_key($oldRelations, $newRelations);

        $modified = [];
        foreach ($common as $relationName => $oldRelation) {
            $newRelation = $newRelations[$relationName];
            $relationDiff = $this->compareRelationship($oldRelation, $newRelation);
            if ($relationDiff !== []) {
                $modified[$relationName] = $relationDiff;
            }
        }

        return [
            'added' => $this->relationshipsToArray($added),
            'removed' => $this->relationshipsToArray($removed),
            'modified' => $modified,
        ];
    }

    /**
     * Compare a single relationship
     */
    private function compareRelationship(Relationship $oldRelation, Relationship $newRelation): array
    {
        $changes = [];

        if ($oldRelation->type !== $newRelation->type) {
            $changes['type'] = [
                'old' => $oldRelation->type,
                'new' => $newRelation->type,
                'breaking' => true, // Relationship type changes are usually breaking
            ];
        }

        if ($oldRelation->model !== $newRelation->model) {
            $changes['model'] = [
                'old' => $oldRelation->model,
                'new' => $newRelation->model,
                'breaking' => true,
            ];
        }

        if ($oldRelation->foreignKey !== $newRelation->foreignKey) {
            $changes['foreign_key'] = [
                'old' => $oldRelation->foreignKey,
                'new' => $newRelation->foreignKey,
                'breaking' => true,
            ];
        }

        if ($oldRelation->localKey !== $newRelation->localKey) {
            $changes['local_key'] = [
                'old' => $oldRelation->localKey,
                'new' => $newRelation->localKey,
                'breaking' => true,
            ];
        }

        return $changes;
    }

    /**
     * Analyze migration impact
     */
    private function analyzeMigrationImpact(ModelSchema $oldSchema, ModelSchema $newSchema): array
    {
        $impact = [
            'requires_migration' => false,
            'migration_operations' => [],
            'data_loss_risk' => 'none', // none, low, medium, high
            'downtime_required' => false,
            'index_changes' => [],
            'constraint_changes' => [],
        ];

        $fieldChanges = $this->compareFields($oldSchema, $newSchema);
        $relationshipChanges = $this->compareRelationships($oldSchema, $newSchema);

        // Check if migration is required
        if (! empty($fieldChanges['added']) || ! empty($fieldChanges['removed']) ||
            ! empty($fieldChanges['modified']) || ! empty($relationshipChanges['added']) ||
            ! empty($relationshipChanges['removed']) || ! empty($relationshipChanges['modified'])) {
            $impact['requires_migration'] = true;
        }

        // Analyze specific operations
        foreach ($fieldChanges['added'] as $fieldName => $field) {
            $impact['migration_operations'][] = [
                'type' => 'add_column',
                'field' => $fieldName,
                'details' => $field,
            ];
        }

        foreach ($fieldChanges['removed'] as $fieldName => $field) {
            $impact['migration_operations'][] = [
                'type' => 'drop_column',
                'field' => $fieldName,
                'details' => $field,
            ];
            $impact['data_loss_risk'] = 'high';
        }

        foreach ($fieldChanges['modified'] as $fieldName => $changes) {
            $impact['migration_operations'][] = [
                'type' => 'modify_column',
                'field' => $fieldName,
                'details' => $changes,
            ];

            // Assess data loss risk
            if (isset($changes['type']) || isset($changes['length']) && $changes['length']['breaking']) {
                $impact['data_loss_risk'] = $impact['data_loss_risk'] === 'high' ? 'high' : 'medium';
            }
        }

        return $impact;
    }

    /**
     * Analyze validation impact using the AutoValidationService
     */
    private function analyzeValidationImpact(ModelSchema $oldSchema, ModelSchema $newSchema): array
    {
        try {
            $autoValidator = new AutoValidationService(new \Grazulex\LaravelModelschema\Support\FieldTypePluginManager());

            $oldRules = $autoValidator->generateValidationRules($oldSchema);
            $newRules = $autoValidator->generateValidationRules($newSchema);

            $addedRules = array_diff_key($newRules, $oldRules);
            $removedRules = array_diff_key($oldRules, $newRules);
            $modifiedRules = [];

            foreach (array_intersect_key($oldRules, $newRules) as $field => $oldFieldRules) {
                $newFieldRules = $newRules[$field];
                if ($oldFieldRules !== $newFieldRules) {
                    $modifiedRules[$field] = [
                        'old' => $oldFieldRules,
                        'new' => $newFieldRules,
                        'added_rules' => array_diff($newFieldRules, $oldFieldRules),
                        'removed_rules' => array_diff($oldFieldRules, $newFieldRules),
                    ];
                }
            }

            return [
                'rules_changed' => $addedRules !== [] || $removedRules !== [] || $modifiedRules !== [],
                'added_validation' => $addedRules,
                'removed_validation' => $removedRules,
                'modified_validation' => $modifiedRules,
                'breaking_validation_changes' => $this->identifyBreakingValidationChanges($modifiedRules),
            ];
        } catch (Exception $e) {
            $this->logger->logError('Failed to analyze validation impact: '.$e->getMessage());

            return [
                'rules_changed' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Identify breaking changes
     */
    private function identifyBreakingChanges(ModelSchema $oldSchema, ModelSchema $newSchema): array
    {
        $breakingChanges = [];

        // Table name change
        if ($oldSchema->table !== $newSchema->table) {
            $breakingChanges[] = [
                'type' => 'table_renamed',
                'description' => "Table renamed from '{$oldSchema->table}' to '{$newSchema->table}'",
                'impact' => 'high',
                'category' => 'schema',
            ];
        }

        // Field changes
        $fieldChanges = $this->compareFields($oldSchema, $newSchema);

        foreach ($fieldChanges['removed'] as $fieldName => $field) {
            $breakingChanges[] = [
                'type' => 'field_removed',
                'field' => $fieldName,
                'description' => "Field '{$fieldName}' was removed",
                'impact' => 'high',
                'category' => 'field',
            ];
        }

        foreach ($fieldChanges['modified'] as $fieldName => $changes) {
            foreach ($changes as $changeType => $changeData) {
                if (isset($changeData['breaking']) && $changeData['breaking']) {
                    $breakingChanges[] = [
                        'type' => 'field_modified',
                        'field' => $fieldName,
                        'change_type' => $changeType,
                        'description' => "Breaking change in field '{$fieldName}': {$changeType}",
                        'old_value' => $changeData['old'],
                        'new_value' => $changeData['new'],
                        'impact' => $this->determineImpactLevel($changeType),
                        'category' => 'field',
                    ];
                }
            }
        }

        // Relationship changes
        $relationshipChanges = $this->compareRelationships($oldSchema, $newSchema);

        foreach ($relationshipChanges['removed'] as $relationName => $relation) {
            $breakingChanges[] = [
                'type' => 'relationship_removed',
                'relationship' => $relationName,
                'description' => "Relationship '{$relationName}' was removed",
                'impact' => 'high',
                'category' => 'relationship',
            ];
        }

        foreach ($relationshipChanges['modified'] as $relationName => $changes) {
            foreach ($changes as $changeType => $changeData) {
                if (isset($changeData['breaking']) && $changeData['breaking']) {
                    $breakingChanges[] = [
                        'type' => 'relationship_modified',
                        'relationship' => $relationName,
                        'change_type' => $changeType,
                        'description' => "Breaking change in relationship '{$relationName}': {$changeType}",
                        'impact' => 'medium',
                        'category' => 'relationship',
                    ];
                }
            }
        }

        return $breakingChanges;
    }

    /**
     * Helper methods
     */
    private function countModifiedFields(array $oldFields, array $newFields): int
    {
        $count = 0;
        foreach (array_intersect_key($oldFields, $newFields) as $fieldName => $oldField) {
            $newField = $newFields[$fieldName];
            if ($this->compareField($oldField, $newField) !== []) {
                $count++;
            }
        }

        return $count;
    }

    private function countModifiedRelationships(array $oldRelations, array $newRelations): int
    {
        $count = 0;
        foreach (array_intersect_key($oldRelations, $newRelations) as $relationName => $oldRelation) {
            $newRelation = $newRelations[$relationName];
            if ($this->compareRelationship($oldRelation, $newRelation) !== []) {
                $count++;
            }
        }

        return $count;
    }

    private function determineCompatibility(ModelSchema $oldSchema, ModelSchema $newSchema): string
    {
        $breakingChanges = $this->identifyBreakingChanges($oldSchema, $newSchema);

        if ($breakingChanges === []) {
            return 'fully_compatible';
        }

        $highImpactChanges = array_filter($breakingChanges, fn ($change): bool => $change['impact'] === 'high');
        if ($highImpactChanges !== []) {
            return 'incompatible';
        }

        return 'partially_compatible';
    }

    private function isTypeChangeBreaking(string $oldType, string $newType): bool
    {
        // Define compatible type transitions
        $compatibleTransitions = [
            'string' => ['text', 'longText', 'mediumText'],
            'text' => ['longText', 'mediumText'],
            'mediumText' => ['longText'],
            'integer' => ['bigInteger'],
            'smallInteger' => ['integer', 'bigInteger'],
            'tinyInteger' => ['smallInteger', 'integer', 'bigInteger'],
            'float' => ['double'],
            'dateTime' => ['timestamp'],
        ];

        return ! isset($compatibleTransitions[$oldType]) ||
               ! in_array($newType, $compatibleTransitions[$oldType]);
    }

    private function compareArrays(array $old, array $new): array
    {
        $diff = [];

        $added = array_diff_key($new, $old);
        if ($added !== []) {
            $diff['added'] = $added;
        }

        $removed = array_diff_key($old, $new);
        if ($removed !== []) {
            $diff['removed'] = $removed;
        }

        $modified = [];
        foreach (array_intersect_key($old, $new) as $key => $oldValue) {
            $newValue = $new[$key];
            if ($oldValue !== $newValue) {
                $modified[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        if ($modified !== []) {
            $diff['modified'] = $modified;
        }

        return $diff;
    }

    private function fieldsToArray(array $fields): array
    {
        return array_map(fn (Field $field): array => $field->toArray(), $fields);
    }

    private function relationshipsToArray(array $relationships): array
    {
        return array_map(fn (Relationship $relationship): array => $relationship->toArray(), $relationships);
    }

    private function countTotalChanges(array $diff): int
    {
        $count = 0;
        $count += count($diff['field_changes']['added']);
        $count += count($diff['field_changes']['removed']);
        $count += count($diff['field_changes']['modified']);
        $count += count($diff['relationship_changes']['added']);
        $count += count($diff['relationship_changes']['removed']);

        return $count + count($diff['relationship_changes']['modified']);
    }

    private function identifyBreakingValidationChanges(array $modifiedRules): array
    {
        $breaking = [];

        foreach ($modifiedRules as $field => $changes) {
            // Adding required rule is breaking if field wasn't required before
            if (in_array('required', $changes['added_rules']) &&
                (in_array('nullable', $changes['removed_rules']) || ! in_array('required', $changes['old']))) {
                $breaking[] = [
                    'field' => $field,
                    'change' => 'field_made_required',
                    'description' => "Field '{$field}' was made required",
                ];
            }

            // Making field shorter is breaking
            foreach ($changes['added_rules'] as $rule) {
                if (preg_match('/^max:(\d+)$/', $rule, $matches)) {
                    $newMax = (int) $matches[1];
                    foreach ($changes['removed_rules'] as $oldRule) {
                        if (preg_match('/^max:(\d+)$/', $oldRule, $oldMatches)) {
                            $oldMax = (int) $oldMatches[1];
                            if ($newMax < $oldMax) {
                                $breaking[] = [
                                    'field' => $field,
                                    'change' => 'max_length_reduced',
                                    'description' => "Maximum length for '{$field}' reduced from {$oldMax} to {$newMax}",
                                ];
                            }
                        }
                    }
                }
            }
        }

        return $breaking;
    }

    private function determineImpactLevel(string $changeType): string
    {
        $highImpact = ['type', 'nullable'];
        $mediumImpact = ['unique', 'length', 'precision', 'scale'];

        if (in_array($changeType, $highImpact)) {
            return 'high';
        }

        if (in_array($changeType, $mediumImpact)) {
            return 'medium';
        }

        return 'low';
    }
}
