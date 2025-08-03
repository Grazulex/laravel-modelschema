<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\Examples;

use Grazulex\LaravelModelschema\Services\LoggingService;
use Grazulex\LaravelModelschema\Services\SchemaCacheService;
use Grazulex\LaravelModelschema\Services\SchemaService;
use Grazulex\LaravelModelschema\Services\YamlOptimizationService;

/**
 * Exemples d'utilisation des optimisations YAML pour améliorer les performances
 * lors du traitement de gros schémas YAML.
 */
class YamlOptimizationExamples
{
    private SchemaService $schemaService;

    private YamlOptimizationService $optimizer;

    public function __construct()
    {
        $this->schemaService = new SchemaService();
        $this->optimizer = new YamlOptimizationService(
            new LoggingService(),
            new SchemaCacheService()
        );
    }

    /**
     * Exemple 1: Parsing standard vs optimisé
     */
    public function basicOptimizationExample(): void
    {
        echo "=== Exemple 1: Parsing Standard vs Optimisé ===\n";

        $yamlContent = '
model: User
fields:
  name:
    type: string
    max_length: 255
  email:
    type: email
    unique: true
  password:
    type: string
    min_length: 8
  created_at:
    type: timestamp
  updated_at:
    type: timestamp
relationships:
  posts:
    type: hasMany
    model: Post
  profile:
    type: hasOne
    model: Profile
';

        // Parsing avec optimisations automatiques
        $startTime = microtime(true);
        $result = $this->schemaService->parseYamlOptimized($yamlContent);
        $endTime = microtime(true);

        $executionTime = ($endTime - $startTime) * 1000;

        echo "Contenu parsé en {$executionTime}ms\n";
        echo "Modèle: {$result['model']}\n";
        echo 'Nombre de champs: '.count($result['fields'])."\n";
        echo 'Nombre de relations: '.count($result['relationships'] ?? [])."\n";

        // Afficher les métriques de performance
        $metrics = $this->schemaService->getYamlPerformanceMetrics();
        echo 'Métriques: '.json_encode($metrics, JSON_PRETTY_PRINT)."\n\n";
    }

    /**
     * Exemple 2: Parsing sélectif par section
     */
    public function sectionOnlyParsingExample(): void
    {
        echo "=== Exemple 2: Parsing Sélectif par Section ===\n";

        $complexYaml = "
model: ComplexModel
fields:
  id:
    type: bigInteger
    primary: true
  name:
    type: string
  description:
    type: longText
relationships:
  categories:
    type: belongsToMany
    model: Category
    pivot:
      - status
      - priority
metadata:
  version: 2.1
  author: 'Development Team'
  tags:
    - ecommerce
    - products
ui:
  form_layout: tabs
  list_columns:
    - name
    - status
    - created_at
  filters:
    - category
    - status
permissions:
  roles:
    - admin
    - manager
    - viewer
  policies:
    - create
    - update
    - delete
";

        // Ne parser que les champs - plus rapide pour la validation
        $startTime = microtime(true);
        $fieldsOnly = $this->schemaService->parseSectionOnly($complexYaml, 'fields');
        $endTime = microtime(true);

        echo "Section 'fields' parsée en ".(($endTime - $startTime) * 1000)."ms\n";
        echo 'Champs trouvés: '.implode(', ', array_keys($fieldsOnly))."\n";

        // Parser seulement les relations
        $relationshipsOnly = $this->schemaService->parseSectionOnly($complexYaml, 'relationships');
        echo 'Relations trouvées: '.implode(', ', array_keys($relationshipsOnly))."\n";

        // Parser seulement les métadonnées
        $metadataOnly = $this->schemaService->parseSectionOnly($complexYaml, 'metadata');
        echo "Version du schéma: {$metadataOnly['version']}\n\n";
    }

    /**
     * Exemple 3: Validation rapide sans parsing complet
     */
    public function quickValidationExample(): void
    {
        echo "=== Exemple 3: Validation Rapide ===\n";

        $problematicYaml = "
model: ProblematicModel
fields:
\tname:  # Tab character instead of spaces
    type: string
  email:
 type: email  # Inconsistent indentation
relationships:
  user:
    type: belongsTo
    model: User\x00  # Control character
";

        $validation = $this->schemaService->quickValidateYaml($problematicYaml);

        echo "Validation rapide terminée\n";
        echo 'Erreurs: '.count($validation['errors'])."\n";
        echo 'Avertissements: '.count($validation['warnings'])."\n";

        if (! empty($validation['warnings'])) {
            echo "Détails des avertissements:\n";
            foreach ($validation['warnings'] as $warning) {
                echo "  - {$warning}\n";
            }
        }

        echo "\n";
    }

    /**
     * Exemple 4: Gestion de gros fichiers avec streaming
     */
    public function largeFileHandlingExample(): void
    {
        echo "=== Exemple 4: Gestion de Gros Fichiers ===\n";

        // Simuler un très gros schéma YAML
        $largeYaml = "model: LargeDataModel\nfields:\n";

        // Ajouter 1000 champs
        for ($i = 1; $i <= 1000; $i++) {
            $largeYaml .= "  field_{$i}:\n";
            $largeYaml .= "    type: string\n";
            $largeYaml .= "    description: 'Auto-generated field number {$i}'\n";
            $largeYaml .= "    max_length: 255\n";
            $largeYaml .= "    nullable: true\n";
        }

        $largeYaml .= "relationships:\n";
        // Ajouter 100 relations
        for ($i = 1; $i <= 100; $i++) {
            $largeYaml .= "  related_model_{$i}:\n";
            $largeYaml .= "    type: hasMany\n";
            $largeYaml .= "    model: RelatedModel{$i}\n";
        }

        echo 'Taille du fichier YAML: '.number_format(mb_strlen($largeYaml))." caractères\n";

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        // Parsing optimisé avec gestion automatique du streaming
        $result = $this->schemaService->parseYamlOptimized($largeYaml);

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        $executionTime = ($endTime - $startTime) * 1000;
        $memoryUsed = $endMemory - $startMemory;

        echo "Parsing terminé en {$executionTime}ms\n";
        echo 'Mémoire utilisée: '.number_format($memoryUsed / 1024)." KB\n";
        echo 'Nombre de champs parsés: '.count($result['fields'])."\n";
        echo 'Nombre de relations parsées: '.count($result['relationships'])."\n";

        $metrics = $this->schemaService->getYamlPerformanceMetrics();
        if ($metrics['streaming_parses'] > 0) {
            echo "Parsing en streaming utilisé: Oui\n";
        } elseif ($metrics['lazy_loads'] > 0) {
            echo "Parsing paresseux utilisé: Oui\n";
        } else {
            echo "Parsing standard utilisé: Oui\n";
        }

        echo "\n";
    }

    /**
     * Exemple 5: Analyse de performance avec cache
     */
    public function cachePerformanceExample(): void
    {
        echo "=== Exemple 5: Performance avec Cache ===\n";

        $yamlContent = '
model: CacheTestModel
fields:
  name:
    type: string
  email:
    type: email
  status:
    type: enum
    values: [active, inactive, pending]
';

        // Nettoyer le cache pour un test propre
        $this->schemaService->clearYamlOptimizationCache();

        echo "Test de performance avec 10 parsings identiques:\n";

        $times = [];
        for ($i = 1; $i <= 10; $i++) {
            $startTime = microtime(true);
            $result = $this->schemaService->parseYamlOptimized($yamlContent);
            $endTime = microtime(true);

            $executionTime = ($endTime - $startTime) * 1000;
            $times[] = $executionTime;

            echo "Parsing #{$i}: {$executionTime}ms";
            if ($i === 1) {
                echo ' (cache miss)';
            } else {
                echo ' (cache hit)';
            }
            echo "\n";
        }

        $averageTime = array_sum($times) / count($times);
        $firstParse = $times[0];
        $averageCachedTime = array_sum(array_slice($times, 1)) / (count($times) - 1);

        echo "\nStatistiques:\n";
        echo "Premier parsing (cache miss): {$firstParse}ms\n";
        echo "Moyenne des parsings suivants (cache hit): {$averageCachedTime}ms\n";
        echo 'Amélioration: '.round((($firstParse - $averageCachedTime) / $firstParse) * 100, 1)."%\n";

        $finalMetrics = $this->schemaService->getYamlPerformanceMetrics();
        echo "Taux de cache hit: {$finalMetrics['cache_hit_rate']}%\n\n";
    }

    /**
     * Exemple 6: Optimisation pour différents types d'utilisation
     */
    public function usagePatternsExample(): void
    {
        echo "=== Exemple 6: Optimisation selon le Pattern d'Usage ===\n";

        $fullSchemaYaml = '
model: OptimizationTestModel
fields:
  id:
    type: bigInteger
    primary: true
  name:
    type: string
    max_length: 255
  description:
    type: longText
  price:
    type: decimal
    precision: 10
    scale: 2
relationships:
  category:
    type: belongsTo
    model: Category
  tags:
    type: belongsToMany
    model: Tag
metadata:
  version: 1.0
  schema_type: ecommerce
validation:
  rules:
    name: required|string|max:255
    price: required|numeric|min:0
ui:
  form_sections:
    - basic_info
    - pricing
    - relationships
';

        echo "1. Validation rapide (structure seulement):\n";
        $startTime = microtime(true);
        $quickValidation = $this->schemaService->quickValidateYaml($fullSchemaYaml);
        $endTime = microtime(true);
        echo '   Temps: '.(($endTime - $startTime) * 1000)."ms\n";
        echo '   Résultat: '.(empty($quickValidation['errors']) ? 'Valide' : 'Erreurs détectées')."\n";

        echo "\n2. Parsing des champs seulement (pour génération de migration):\n";
        $startTime = microtime(true);
        $fieldsOnly = $this->schemaService->parseSectionOnly($fullSchemaYaml, 'fields');
        $endTime = microtime(true);
        echo '   Temps: '.(($endTime - $startTime) * 1000)."ms\n";
        echo '   Champs: '.count($fieldsOnly)."\n";

        echo "\n3. Parsing des relations seulement (pour génération de modèle):\n";
        $startTime = microtime(true);
        $relationshipsOnly = $this->schemaService->parseSectionOnly($fullSchemaYaml, 'relationships');
        $endTime = microtime(true);
        echo '   Temps: '.(($endTime - $startTime) * 1000)."ms\n";
        echo '   Relations: '.count($relationshipsOnly)."\n";

        echo "\n4. Parsing complet optimisé:\n";
        $startTime = microtime(true);
        $fullResult = $this->schemaService->parseYamlOptimized($fullSchemaYaml);
        $endTime = microtime(true);
        echo '   Temps: '.(($endTime - $startTime) * 1000)."ms\n";
        echo '   Sections: '.count($fullResult)."\n";

        echo "\n5. Deuxième parsing complet (cache hit):\n";
        $startTime = microtime(true);
        $cachedResult = $this->schemaService->parseYamlOptimized($fullSchemaYaml);
        $endTime = microtime(true);
        echo '   Temps: '.(($endTime - $startTime) * 1000)."ms\n";
        echo '   Identique: '.($fullResult === $cachedResult ? 'Oui' : 'Non')."\n";

        echo "\n";
    }

    /**
     * Exemple 7: Monitoring et métriques avancées
     */
    public function monitoringExample(): void
    {
        echo "=== Exemple 7: Monitoring et Métriques ===\n";

        // Simuler différents types d'utilisation
        $smallYaml = "model: Small\nfields:\n  name:\n    type: string";
        $mediumYaml = str_repeat($smallYaml."\n", 50);
        $largeYaml = str_repeat($mediumYaml."\n", 20);

        // Tests variés
        $this->schemaService->parseYamlOptimized($smallYaml);
        $this->schemaService->parseYamlOptimized($mediumYaml);
        $this->schemaService->parseYamlOptimized($largeYaml);
        $this->schemaService->parseYamlOptimized($smallYaml); // Cache hit
        $this->schemaService->parseSectionOnly($mediumYaml, 'model');

        $metrics = $this->schemaService->getYamlPerformanceMetrics();

        echo "Métriques de performance détaillées:\n";
        echo "- Total de parsings: {$metrics['total_parses']}\n";
        echo "- Cache hits: {$metrics['cache_hits']}\n";
        echo "- Cache misses: {$metrics['cache_misses']}\n";
        echo "- Taux de cache hit: {$metrics['cache_hit_rate']}%\n";
        echo "- Parsings paresseux: {$metrics['lazy_loads']}\n";
        echo "- Parsings streaming: {$metrics['streaming_parses']}\n";

        if (isset($metrics['lazy_load_rate'])) {
            echo "- Taux de parsing paresseux: {$metrics['lazy_load_rate']}%\n";
        }

        if (isset($metrics['memory_saved_bytes']) && $metrics['memory_saved_bytes'] > 0) {
            echo '- Mémoire économisée: '.number_format($metrics['memory_saved_bytes'] / 1024)." KB\n";
        }

        if (isset($metrics['time_saved_ms']) && $metrics['time_saved_ms'] > 0) {
            echo '- Temps économisé: '.number_format($metrics['time_saved_ms'])." ms\n";
        }

        echo "\n";
    }

    /**
     * Exécuter tous les exemples
     */
    public function runAllExamples(): void
    {
        echo "🚀 EXEMPLES D'OPTIMISATION YAML POUR LARAVEL MODELSCHEMA\n";
        echo str_repeat('=', 60)."\n\n";

        $this->basicOptimizationExample();
        $this->sectionOnlyParsingExample();
        $this->quickValidationExample();
        $this->largeFileHandlingExample();
        $this->cachePerformanceExample();
        $this->usagePatternsExample();
        $this->monitoringExample();

        echo "✅ Tous les exemples terminés avec succès !\n";
        echo "💡 Ces optimisations permettent de traiter efficacement des schémas YAML de toute taille.\n";
    }
}

// Usage direct du fichier
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'] ?? '')) {
    $examples = new YamlOptimizationExamples();
    $examples->runAllExamples();
}
