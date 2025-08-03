<?php

declare(strict_types=1);

/**
 * Exemples d'utilisation du système de logging ModelSchema
 *
 * Ce fichier montre comment utiliser et configurer le système de logging
 * détaillé du package Laravel ModelSchema.
 */

require_once __DIR__.'/../vendor/autoload.php';

use Grazulex\LaravelModelschema\Services\Generation\GenerationService;
use Grazulex\LaravelModelschema\Services\LoggingService;
use Grazulex\LaravelModelschema\Services\SchemaService;
use Illuminate\Support\Facades\Log;

echo "=== Exemples d'utilisation du système de logging ModelSchema ===\n\n";

// 1. Configuration basique du logging
echo "1. Configuration du logging\n";
echo "----------------------------\n";

// Le service de logging est automatiquement configuré via config/modelschema.php
// Vous pouvez également le configurer manuellement :

$logger = new LoggingService();

// Vérifier si le logging est activé
if ($logger->isEnabled()) {
    echo "✅ Logging activé\n";
    echo '📋 Session ID: '.$logger->getSessionId()."\n";
} else {
    echo "❌ Logging désactivé\n";
}

// Désactiver temporairement le logging
$logger->setEnabled(false);
echo "🔇 Logging temporairement désactivé\n";

// Réactiver le logging
$logger->setEnabled(true);
echo "🔊 Logging réactivé\n\n";

// 2. Logging manuel d'opérations
echo "2. Logging manuel d'opérations\n";
echo "-------------------------------\n";

// Démarrage d'une opération
$logger->logOperationStart('exemple_operation', [
    'param1' => 'valeur1',
    'param2' => 42,
]);

// Simulation d'un travail
usleep(100000); // 100ms

// Log de debug pendant l'opération
$logger->logDebug('Traitement en cours', [
    'progress' => '50%',
    'items_processed' => 25,
    'items_total' => 50,
]);

// Log d'avertissement
$logger->logWarning(
    'Opération lente détectée',
    ['operation' => 'exemple_operation', 'duration_ms' => 100],
    'Considérer l\'optimisation du code'
);

// Fin de l\'opération avec métriques
$logger->logOperationEnd('exemple_operation', [
    'items_processed' => 50,
    'success_rate' => 98.5,
    'memory_used' => '45MB',
]);

echo "📝 Opération loggée avec détails complets\n\n";

// 3. Logging de validation
echo "3. Logging de validation\n";
echo "------------------------\n";

// Validation réussie
$logger->logValidation(
    'schema_validation',
    true,
    [], // pas d'erreurs
    ['deprecated_field' => 'Le champ "old_field" est déprécié'], // warnings
    ['fields_validated' => 15, 'relations_validated' => 3] // stats
);

// Validation échouée
$logger->logValidation(
    'rules_validation',
    false,
    ['Field "email" is required', 'Invalid relationship type'], // erreurs
    ['Performance issue with "exists" rule'], // warnings
    ['rules_checked' => 25, 'errors_found' => 2] // stats
);

echo "✅ Validations loggées\n\n";

// 4. Logging de génération
echo "4. Logging de génération\n";
echo "------------------------\n";

$logger->logGeneration(
    'model',
    'User',
    true,
    ['file_size' => 2048, 'generation_time_ms' => 45]
);

$logger->logGeneration(
    'migration',
    'CreateUsersTable',
    false,
    ['error' => 'Template not found', 'attempted_path' => '/templates/migration.stub']
);

echo "🎯 Générations loggées\n\n";

// 5. Logging de parsing YAML
echo "5. Logging de parsing YAML\n";
echo "---------------------------\n";

$logger->logYamlParsing(
    'user.schema.yml',
    true,
    [
        'parse_time_ms' => 23,
        'file_size_bytes' => 1024,
        'fields_found' => 8,
        'relations_found' => 2,
    ]
);

$logger->logYamlParsing(
    'invalid.schema.yml',
    false,
    [],
    ['YAML syntax error at line 15']
);

echo "📄 Parsing YAML loggé\n\n";

// 6. Logging de cache
echo "6. Logging de cache\n";
echo "-------------------\n";

$logger->logCache('hit', 'schema:user', true, 0.001);
$logger->logCache('miss', 'schema:product', false, 0.002);
$logger->logCache('store', 'schema:product', false, 0.015);
$logger->logCache('clear', 'all_schemas', false);

echo "💾 Opérations de cache loggées\n\n";

// 7. Logging de performance
echo "7. Logging de performance\n";
echo "-------------------------\n";

$logger->logPerformance('complete_generation', [
    'total_time_ms' => 1250,
    'files_generated' => 7,
    'peak_memory_mb' => 89,
    'cache_hits' => 12,
    'cache_misses' => 3,
]);

echo "📊 Métriques de performance loggées\n\n";

// 8. Logging d'erreurs
echo "8. Logging d'erreurs\n";
echo "--------------------\n";

try {
    throw new Exception('Erreur simulée pour démonstration');
} catch (Exception $e) {
    $logger->logError(
        'Erreur lors du traitement',
        $e,
        ['context' => 'exemple', 'user_id' => 123]
    );
}

echo "❌ Erreur loggée avec trace complète\n\n";

// 9. Intégration avec SchemaService
echo "9. Intégration avec SchemaService\n";
echo "---------------------------------\n";

// Le SchemaService utilise automatiquement le logging
$schemaService = new SchemaService();

// Créer un fichier de test temporaire
$testYaml = <<<'YAML'
model: TestModel
table: test_models
fields:
  id:
    type: unsignedBigInteger
    primary: true
  name:
    type: string
    length: 255
relationships:
  posts:
    type: hasMany
    model: Post
YAML;

$tempFile = tempnam(sys_get_temp_dir(), 'test_schema');
file_put_contents($tempFile, $testYaml);

echo '📁 Fichier test créé: '.basename($tempFile)."\n";

// Le parsing sera automatiquement loggé
try {
    $schema = $schemaService->parseYamlFile($tempFile);
    echo '✅ Schema parsé: '.$schema->name."\n";

    // La validation sera automatiquement loggée
    $errors = $schemaService->validateSchema($schema);
    echo '✅ Schema validé: '.(empty($errors) ? 'Aucune erreur' : count($errors).' erreurs')."\n";

} catch (Exception $e) {
    echo '❌ Erreur: '.$e->getMessage()."\n";
}

// Nettoyage
unlink($tempFile);
echo "🧹 Fichier test supprimé\n\n";

// 10. Intégration avec GenerationService
echo "10. Intégration avec GenerationService\n";
echo "--------------------------------------\n";

if (isset($schema)) {
    $generationService = new GenerationService();

    // La génération sera automatiquement loggée
    try {
        $result = $generationService->generateModel($schema);
        echo "✅ Modèle généré avec succès\n";

        $allResults = $generationService->generateAll($schema, [
            'model' => true,
            'migration' => true,
        ]);
        echo '✅ Génération complète: '.count($allResults)." éléments\n";

    } catch (Exception $e) {
        echo '❌ Erreur de génération: '.$e->getMessage()."\n";
    }
}

echo "\n=== Configuration avancée ===\n";

echo <<<CONFIG
Pour configurer le logging en détail, ajoutez dans config/logging.php :

'channels' => [
    'modelschema' => [
        'driver' => 'daily',
        'path' => storage_path('logs/modelschema.log'),
        'level' => env('MODELSCHEMA_LOG_LEVEL', 'debug'),
        'days' => 14,
    ],
    
    'modelschema_performance' => [
        'driver' => 'single',
        'path' => storage_path('logs/modelschema-performance.log'),
        'level' => 'info',
        'formatter' => Monolog\Formatter\JsonFormatter::class,
    ],
],

Et dans config/modelschema.php :

'logging' => [
    'enabled' => env('MODELSCHEMA_LOGGING_ENABLED', true),
    'channel' => env('MODELSCHEMA_LOG_CHANNEL', 'modelschema'),
    'performance_thresholds' => [
        'yaml_parsing_ms' => 1000,
        'validation_ms' => 2000,
        'generation_ms' => 3000,
    ],
],

Variables d'environnement (.env) :
MODELSCHEMA_LOGGING_ENABLED=true
MODELSCHEMA_LOG_CHANNEL=modelschema
MODELSCHEMA_LOG_LEVEL=debug

CONFIG;

echo "\n=== Exemple de sortie de log ===\n";

echo <<<'LOG'
[2024-12-19 10:30:15] modelschema.INFO: 🚀 Starting parseYamlFile {"session_id":"a1b2c3d4","operation":"parseYamlFile","context":{"file":"user.schema.yml"},"timestamp":"2024-12-19T10:30:15.123456Z","memory_usage":"45.2 MB","context_depth":1}

[2024-12-19 10:30:15] modelschema.DEBUG: ⚡ Cache miss: user.schema.yml {"session_id":"a1b2c3d4","cache_operation":"miss","cache_key":"user.schema.yml","cache_hit":false,"current_operation":"parseYamlFile"}

[2024-12-19 10:30:15] modelschema.INFO: 📄 YAML Parsing: user.schema.yml {"session_id":"a1b2c3d4","source":"user.schema.yml","success":true,"statistics":{"parse_time_ms":23.45,"field_count":8,"relationship_count":2,"file_size":1024},"current_operation":"parseYamlFile","memory_usage":"45.8 MB"}

[2024-12-19 10:30:15] modelschema.DEBUG: 💾 Cache store: user.schema.yml {"session_id":"a1b2c3d4","cache_operation":"store","cache_key":"user.schema.yml","cache_hit":false,"duration_ms":1.23,"current_operation":"parseYamlFile"}

[2024-12-19 10:30:15] modelschema.INFO: ✅ Completed parseYamlFile {"session_id":"a1b2c3d4","operation":"parseYamlFile","duration_ms":45.67,"memory_usage":"46.1 MB","memory_peak":"46.3 MB","metrics":{"source":"parse","parse_time_ms":23.45,"cache_store_time_ms":1.23,"field_count":8,"relationship_count":2},"context_depth":0}

LOG;

echo "\n✨ Système de logging ModelSchema prêt à l'emploi !\n";
echo "📖 Consultez les logs dans storage/logs/modelschema.log\n";
echo "🔍 Activez le debug avec MODELSCHEMA_LOG_LEVEL=debug\n";
echo "📊 Surveillez les performances avec les seuils configurés\n\n";
