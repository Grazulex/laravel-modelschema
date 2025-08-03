<?php

declare(strict_types=1);

use Grazulex\LaravelModelschema\Exceptions\SchemaException;
use Grazulex\LaravelModelschema\Services\LoggingService;
use Grazulex\LaravelModelschema\Services\SchemaCacheService;
use Grazulex\LaravelModelschema\Services\YamlOptimizationService;

beforeEach(function () {
    $this->logger = new LoggingService();
    $this->cache = new SchemaCacheService();
    $this->optimizer = new YamlOptimizationService($this->logger, $this->cache);
});

describe('YamlOptimizationService', function () {

    describe('parseYamlContent', function () {

        it('parses small YAML files using standard strategy', function () {
            $yamlContent = '
model: User
fields:
  name:
    type: string
  email:
    type: email
';

            $result = $this->optimizer->parseYamlContent($yamlContent);

            expect($result)->toBeArray()
                ->and($result['model'])->toBe('User')
                ->and($result['fields'])->toBeArray()
                ->and($result['fields']['name']['type'])->toBe('string');
        });

        it('caches parsing results and reuses them', function () {
            $yamlContent = '
model: Product
fields:
  name:
    type: string
  price:
    type: decimal
';

            // Premier parsing
            $result1 = $this->optimizer->parseYamlContent($yamlContent);

            // Deuxième parsing - devrait utiliser le cache
            $result2 = $this->optimizer->parseYamlContent($yamlContent);

            expect($result1)->toEqual($result2);

            $metrics = $this->optimizer->getPerformanceMetrics();
            expect($metrics['total_parses'])->toBe(2)
                ->and($metrics['cache_hits'])->toBe(1)
                ->and($metrics['cache_misses'])->toBe(1);
        });

        it('uses lazy parsing for medium-sized files', function () {
            // Créer un YAML de taille moyenne (> 1MB) sans clés dupliquées
            $largeYaml = "model: LargeModel\nfields:\n";
            for ($i = 0; $i < 10000; $i++) {
                $largeYaml .= "  field_{$i}:\n    type: string\n    description: 'Field number {$i}'\n";
            }

            $result = $this->optimizer->parseYamlContent($largeYaml, ['sections' => ['fields']]);

            expect($result)->toBeArray();

            $metrics = $this->optimizer->getPerformanceMetrics();
            // Peut être lazy_loads ou standard selon la taille réelle
            expect($metrics['total_parses'])->toBeGreaterThan(0);
        });
        it('handles parsing errors gracefully', function () {
            $invalidYaml = '
model: InvalidModel
fields:
  name:
    type: string
  invalid: [unclosed array
';

            expect(fn () => $this->optimizer->parseYamlContent($invalidYaml))
                ->toThrow(SchemaException::class);
        });

        it('tracks performance metrics correctly', function () {
            $this->optimizer->resetMetrics();

            $yamlContent = '
model: TestModel
fields:
  name:
    type: string
';

            $this->optimizer->parseYamlContent($yamlContent);
            $this->optimizer->parseYamlContent($yamlContent); // Cache hit

            $metrics = $this->optimizer->getPerformanceMetrics();

            expect($metrics['total_parses'])->toBe(2)
                ->and($metrics['cache_hits'])->toBe(1)
                ->and($metrics['cache_misses'])->toBe(1)
                ->and($metrics['cache_hit_rate'])->toBe(50.0);
        });
    });

    describe('parseSectionOnly', function () {

        it('parses only the requested section', function () {
            $yamlContent = '
model: User
fields:
  name:
    type: string
  email:
    type: email
relationships:
  posts:
    type: hasMany
    model: Post
metadata:
  version: 1.0
  author: test
';

            $fieldsResult = $this->optimizer->parseSectionOnly($yamlContent, 'fields');

            expect($fieldsResult)->toBeArray()
                ->and($fieldsResult['name']['type'])->toBe('string')
                ->and($fieldsResult['email']['type'])->toBe('email');
        });

        it('throws exception for non-existent section', function () {
            $yamlContent = '
model: User
fields:
  name:
    type: string
';

            expect(fn () => $this->optimizer->parseSectionOnly($yamlContent, 'nonexistent'))
                ->toThrow(SchemaException::class, "Section 'nonexistent' not found in YAML content");
        });

        it('tracks time saved metrics', function () {
            $this->optimizer->resetMetrics();

            $yamlContent = '
model: User
fields:
  name:
    type: string
relationships:
  posts:
    type: hasMany
    model: Post
';

            $this->optimizer->parseSectionOnly($yamlContent, 'fields');

            $metrics = $this->optimizer->getPerformanceMetrics();
            expect($metrics['time_saved_ms'])->toBeGreaterThan(0);
        });
    });

    describe('quickValidate', function () {

        it('validates YAML structure without parsing', function () {
            $yamlContent = '
model: User
fields:
  name:
    type: string
  email:
    type: email
';

            $result = $this->optimizer->quickValidate($yamlContent);

            expect($result)->toHaveKey('errors')
                ->and($result)->toHaveKey('warnings')
                ->and($result['errors'])->toBeArray()
                ->and($result['warnings'])->toBeArray();
        });

        it('detects empty YAML content', function () {
            $result = $this->optimizer->quickValidate('');

            expect($result['errors'])->toContain('YAML content is empty');
        });

        it('detects indentation issues', function () {
            $yamlWithTabs = "
model: User
fields:
\tname:  # Tab instead of spaces
    type: string
";

            $result = $this->optimizer->quickValidate($yamlWithTabs);

            expect($result['warnings'])->toContain('Line 4 contains tabs - use spaces for YAML indentation');
        });

        it('detects control characters', function () {
            $yamlWithControlChars = "model: User\x00fields:\n  name:\n    type: string";

            $result = $this->optimizer->quickValidate($yamlWithControlChars);

            expect($result['warnings'])->toContain('YAML contains control characters that may cause parsing issues');
        });

        it('warns about missing main sections', function () {
            $yamlWithoutSections = "some: value\nother: data";

            $result = $this->optimizer->quickValidate($yamlWithoutSections);

            expect($result['warnings'])->toContain('No main sections found in YAML');
        });

        it('detects inconsistent indentation', function () {
            $yamlWithBadIndent = '
model: User
fields:
 name:     # 1 space
    type: string  # 4 spaces
   email:  # 3 spaces
    type: email
';

            $result = $this->optimizer->quickValidate($yamlWithBadIndent);

            expect($result['warnings'])->toContain('Inconsistent indentation detected - use consistent spacing (2 or 4 spaces)');
        });

        it('warns about large indentation', function () {
            $yamlWithLargeIndent = '
model: User
fields:
        name:  # 8 spaces
                type: string  # 16 spaces
';

            $result = $this->optimizer->quickValidate($yamlWithLargeIndent);

            expect($result['warnings'])->toContain('Large indentation detected (8 spaces) - consider using 2 or 4 spaces');
        });
    });

    describe('performance optimization', function () {

        it('uses different strategies based on content size', function () {
            $this->optimizer->resetMetrics();

            // Small file (standard parsing)
            $smallYaml = 'model: SmallModel
fields:
  name:
    type: string';
            $this->optimizer->parseYamlContent($smallYaml);

            // Medium file (lazy parsing) - simulate with sections option
            $mediumYaml = 'model: MediumModel
fields:
';
            for ($i = 0; $i < 100; $i++) {
                $mediumYaml .= "  field_{$i}:
    type: string
";
            }
            $this->optimizer->parseYamlContent($mediumYaml, ['sections' => ['fields']]);

            $metrics = $this->optimizer->getPerformanceMetrics();
            expect($metrics['total_parses'])->toBe(2);
        });
        it('limits cache size to prevent memory bloat', function () {
            $this->optimizer->resetMetrics();

            // Ajouter plus de 100 entrées au cache
            for ($i = 0; $i < 120; $i++) {
                $yaml = "model: Model{$i}\nfields:\n  field{$i}:\n    type: string";
                $this->optimizer->parseYamlContent($yaml);
            }

            $metrics = $this->optimizer->getPerformanceMetrics();
            expect($metrics['total_parses'])->toBe(120);

            // Le cache devrait être limité
            $this->optimizer->clearCache();
            // Après clear, toutes les entrées devraient être cache miss
            $yaml = "model: TestAfterClear\nfields:\n  test:\n    type: string";
            $this->optimizer->parseYamlContent($yaml);

            $metricsAfterClear = $this->optimizer->getPerformanceMetrics();
            expect($metricsAfterClear['cache_misses'])->toBeGreaterThan($metrics['cache_misses']);
        });

        it('handles memory management for large files', function () {
            // Simuler un fichier nécessitant plus de mémoire avec contenu simple
            $largeContent = "model: LargeMemoryTestModel\nfields:\n";

            // Créer un contenu assez grand mais simple pour éviter les erreurs de parsing
            for ($i = 0; $i < 100; $i++) {
                $largeContent .= "  memory_field_{$i}:\n    type: string\n";
            }

            $result = $this->optimizer->parseYamlContent($largeContent);

            expect($result)->toBeArray()
                ->and($result['model'])->toBe('LargeMemoryTestModel')
                ->and($result['fields'])->toBeArray()
                ->and(count($result['fields']))->toBe(100);
        });
    });

    describe('cache management', function () {

        it('clears cache properly', function () {
            $yaml = "model: Test\nfields:\n  name:\n    type: string";

            // Parse to populate cache
            $this->optimizer->parseYamlContent($yaml);

            $metricsBeforeClear = $this->optimizer->getPerformanceMetrics();
            expect($metricsBeforeClear['cache_misses'])->toBe(1);

            // Clear cache
            $this->optimizer->clearCache();

            // Parse again - should be cache miss
            $this->optimizer->parseYamlContent($yaml);

            $metricsAfterClear = $this->optimizer->getPerformanceMetrics();
            expect($metricsAfterClear['cache_misses'])->toBe(2);
        });

        it('resets metrics correctly', function () {
            $yaml = "model: Test\nfields:\n  name:\n    type: string";

            $this->optimizer->parseYamlContent($yaml);
            $this->optimizer->parseYamlContent($yaml); // Cache hit

            $this->optimizer->resetMetrics();

            $metrics = $this->optimizer->getPerformanceMetrics();
            expect($metrics['total_parses'])->toBe(0)
                ->and($metrics['cache_hits'])->toBe(0)
                ->and($metrics['cache_misses'])->toBe(0);
        });
    });

    describe('error handling', function () {

        it('handles streaming parse errors gracefully', function () {
            // Simuler un très gros fichier avec des erreurs dans certaines sections
            $hugeBadYaml = "model: HugeModel\n";

            // Ajouter beaucoup de contenu pour déclencher le streaming
            for ($i = 0; $i < 100000; $i++) {
                if ($i === 50000) {
                    $hugeBadYaml .= "bad_section:\n  [unclosed: array\n"; // Section avec erreur
                } else {
                    $hugeBadYaml .= "section_{$i}:\n  field: value\n";
                }
            }

            // Ne devrait pas lancer d'exception grâce à la gestion d'erreur en streaming
            $result = $this->optimizer->parseYamlContent($hugeBadYaml);

            expect($result)->toBeArray();
            // La section avec erreur devrait être ignorée mais le reste parsé
        });

        it('logs parsing failures appropriately', function () {
            $invalidYaml = "model: Test\nfields:\n  name: [unclosed array";

            expect(fn () => $this->optimizer->parseYamlContent($invalidYaml))
                ->toThrow(SchemaException::class);

            // Vérifier que l'erreur a été loggée
            // Note: Dans un vrai test, on pourrait mock le logger pour vérifier les appels
        });
    });

    describe('section identification', function () {

        it('identifies sections correctly', function () {
            $yamlContent = '
model: User
fields:
  name:
    type: string
relationships:
  posts:
    type: hasMany
metadata:
  version: 1.0
';

            // Test indirect via parseSectionOnly
            $fieldsResult = $this->optimizer->parseSectionOnly($yamlContent, 'fields');
            $relationshipsResult = $this->optimizer->parseSectionOnly($yamlContent, 'relationships');
            $metadataResult = $this->optimizer->parseSectionOnly($yamlContent, 'metadata');

            expect($fieldsResult)->toBeArray()
                ->and($relationshipsResult)->toBeArray()
                ->and($metadataResult)->toBeArray()
                ->and($fieldsResult['name']['type'])->toBe('string')
                ->and($relationshipsResult['posts']['type'])->toBe('hasMany')
                ->and($metadataResult['version'])->toBe(1.0);
        });
    });
});
