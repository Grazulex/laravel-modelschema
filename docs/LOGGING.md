# Système de Logging ModelSchema

Le package Laravel ModelSchema inclut un système de logging détaillé pour faciliter le debugging et le monitoring des opérations.

## Configuration

### Activation/Désactivation

```php
// Dans config/modelschema.php
'logging' => [
    'enabled' => env('MODELSCHEMA_LOGGING_ENABLED', true),
    'channel' => env('MODELSCHEMA_LOG_CHANNEL', 'modelschema'),
]
```

### Configuration du canal de log

Ajoutez à votre `config/logging.php` :

```php
'channels' => [
    'modelschema' => [
        'driver' => 'daily',
        'path' => storage_path('logs/modelschema.log'),
        'level' => env('MODELSCHEMA_LOG_LEVEL', 'debug'),
        'days' => 14,
    ],
]
```

### Variables d'environnement

```env
MODELSCHEMA_LOGGING_ENABLED=true
MODELSCHEMA_LOG_CHANNEL=modelschema
MODELSCHEMA_LOG_LEVEL=debug
```

## Types de logs

### 🚀 Opérations
- Début et fin des opérations principales
- Durée d'exécution
- Utilisation mémoire
- Context tracking

### 📄 Parsing YAML
- Temps de parsing
- Taille des fichiers
- Nombre de champs et relations
- Erreurs de syntaxe

### ✅ Validation
- Résultats de validation
- Erreurs et warnings détaillés
- Statistiques de validation
- Règles Laravel validées

### 🎯 Génération
- Succès/échec de génération
- Types de fichiers générés
- Métriques de performance
- Taille des outputs

### 💾 Cache
- Hits et misses
- Opérations de stockage
- Temps d'accès
- Clés utilisées

### ⚠️ Performance
- Seuils de performance configurables
- Warnings automatiques
- Recommandations d'optimisation
- Métriques détaillées

### ❌ Erreurs
- Traces d'exception complètes
- Context d'exécution
- Stack traces
- Données de debugging

## Exemple de sortie

```
[2024-12-19 10:30:15] modelschema.INFO: 🚀 Starting parseYamlFile
{"session_id":"a1b2c3d4","file":"user.schema.yml","memory_usage":"45.2 MB"}

[2024-12-19 10:30:15] modelschema.DEBUG: ⚡ Cache miss: user.schema.yml
{"session_id":"a1b2c3d4","cache_key":"user.schema.yml"}

[2024-12-19 10:30:15] modelschema.INFO: 📄 YAML Parsing: user.schema.yml
{"session_id":"a1b2c3d4","parse_time_ms":23.45,"field_count":8}

[2024-12-19 10:30:15] modelschema.INFO: ✅ Completed parseYamlFile
{"session_id":"a1b2c3d4","duration_ms":45.67,"memory_peak":"46.3 MB"}
```

## Usage programmatique

```php
use Grazulex\LaravelModelschema\Services\LoggingService;

$logger = new LoggingService();

// Logging manuel d'opérations
$logger->logOperationStart('custom_operation', ['param' => 'value']);
$logger->logDebug('Processing items', ['count' => 100]);
$logger->logOperationEnd('custom_operation', ['items_processed' => 100]);

// Logging de performance
$logger->logPerformance('bulk_processing', [
    'items' => 1000,
    'duration_ms' => 2500,
    'memory_peak' => '128MB'
]);

// Logging d'erreurs
try {
    // ... code
} catch (\Exception $e) {
    $logger->logError('Operation failed', $e, ['context' => 'data']);
}
```

## Intégration automatique

Le logging est automatiquement intégré dans :

- ✅ `SchemaService` - Parsing, validation, cache
- ✅ `GenerationService` - Génération de tous les types
- ✅ `EnhancedValidationService` - Validation avancée
- ✅ Tous les générateurs individuels

## Configuration avancée

### Seuils de performance

```php
'performance_thresholds' => [
    'yaml_parsing_ms' => 1000,    // Warning si > 1s
    'validation_ms' => 2000,      // Warning si > 2s
    'generation_ms' => 3000,      // Warning si > 3s
    'memory_usage_mb' => 128,     // Warning si > 128MB
],
```

### Tracking de context

```php
'track_context' => true,
'max_context_depth' => 10,
'include_memory_usage' => true,
'include_timing' => true,
```

### Détails d'erreur

```php
'error_details' => [
    'include_stack_trace' => true,
    'include_context_stack' => true,
    'max_trace_lines' => 20,
],
```

## Monitoring et analyse

Le système de logging facilite :

- 🔍 **Debugging** - Traces détaillées avec context
- 📊 **Performance monitoring** - Métriques et seuils
- 🚨 **Alerting** - Warnings automatiques
- 📈 **Analytics** - Patterns d'usage et optimisation
- 🔧 **Troubleshooting** - Reproduction d'erreurs

## Best practices

1. **Production** : Utilisez `level => 'info'` ou `'warning'`
2. **Development** : Utilisez `level => 'debug'` pour tous les détails
3. **Monitoring** : Configurez des alertes sur les warnings de performance
4. **Retention** : Configurez la rotation des logs appropriée
5. **Analysis** : Utilisez des outils comme ELK Stack pour l'analyse

Le système de logging ModelSchema vous donne une visibilité complète sur le fonctionnement du package pour un debugging efficace et un monitoring proactif.
