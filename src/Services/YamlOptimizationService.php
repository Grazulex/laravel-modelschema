<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\Services;

use Grazulex\LaravelModelschema\Exceptions\SchemaException;
use RuntimeException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Yaml;

/**
 * Service d'optimisation pour le parsing YAML, spécialement conçu pour les gros schémas.
 * Implémente le parsing paresseux, le streaming, et la mise en cache intelligente.
 */
class YamlOptimizationService
{
    /**
     * Taille limite en octets pour déclencher le parsing optimisé
     */
    private const LARGE_FILE_THRESHOLD = 1024 * 1024; // 1MB

    /**
     * Taille limite pour le parsing en streaming
     */
    private const STREAMING_THRESHOLD = 5 * 1024 * 1024; // 5MB

    /**
     * Cache des sections parsées
     */
    private array $sectionCache = [];

    /**
     * Compteurs de performance
     */
    private array $performanceMetrics = [
        'total_parses' => 0,
        'cache_hits' => 0,
        'cache_misses' => 0,
        'lazy_loads' => 0,
        'streaming_parses' => 0,
        'memory_saved_bytes' => 0,
        'time_saved_ms' => 0,
    ];

    public function __construct(
        private LoggingService $logger,
        private SchemaCacheService $cacheService
    ) {}

    /**
     * Parse un contenu YAML avec optimisations automatiques
     */
    public function parseYamlContent(string $content, array $options = []): array
    {
        $this->performanceMetrics['total_parses']++;
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        $this->logger->logOperationStart('optimized_yaml_parse', [
            'content_size' => mb_strlen($content),
            'options' => $options,
        ]);

        try {
            $result = $this->determineParsingStrategy($content, $options);

            $endTime = microtime(true);
            $endMemory = memory_get_usage(true);
            $executionTime = ($endTime - $startTime) * 1000;
            $memoryUsed = $endMemory - $startMemory;

            $this->logger->logPerformance('yaml_parsing', [
                'execution_time_ms' => $executionTime,
                'memory_used_bytes' => $memoryUsed,
                'content_size' => mb_strlen($content),
                'strategy_used' => $result['strategy'] ?? 'standard',
                'cache_hit' => $result['from_cache'] ?? false,
            ]);

            $this->logger->logOperationEnd('optimized_yaml_parse', [
                'success' => true,
                'execution_time_ms' => $executionTime,
                'memory_used_bytes' => $memoryUsed,
                'strategy' => $result['strategy'] ?? 'standard',
            ]);

            return $result['data'];

        } catch (ParseException $e) {
            $this->logger->logError(
                'YAML parsing failed',
                $e,
                ['content_preview' => mb_substr($content, 0, 100)]
            );
            throw new SchemaException("YAML parsing failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Parse seulement une section spécifique d'un YAML
     */
    public function parseSectionOnly(string $content, string $sectionName): array
    {
        $startTime = microtime(true);

        $sections = $this->identifySections($content);
        if (! isset($sections[$sectionName])) {
            throw new SchemaException("Section '{$sectionName}' not found in YAML content");
        }

        $sectionContent = $this->extractSection($content, $sections[$sectionName]);
        $parser = new Parser();
        $result = $parser->parse($sectionContent, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);

        $executionTime = (microtime(true) - $startTime) * 1000;
        $this->performanceMetrics['time_saved_ms'] += $executionTime;

        $this->logger->logPerformance('section_only_parse', [
            'section_name' => $sectionName,
            'execution_time_ms' => $executionTime,
            'content_size' => mb_strlen($content),
            'section_size' => mb_strlen($sectionContent),
        ]);

        return $result[$sectionName] ?? [];
    }

    /**
     * Valide rapidement la structure YAML sans parsing complet
     */
    public function quickValidate(string $content): array
    {
        $errors = [];
        $warnings = [];

        // Vérifications basiques sans parsing
        if (in_array(mb_trim($content), ['', '0'], true)) {
            $errors[] = 'YAML content is empty';

            return ['errors' => $errors, 'warnings' => $warnings];
        }

        // Vérifier l'indentation
        $lines = explode("\n", $content);
        $indentationIssues = $this->checkIndentation($lines);
        $warnings = array_merge($warnings, $indentationIssues);

        // Vérifier les caractères suspects
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $content)) {
            $warnings[] = 'YAML contains control characters that may cause parsing issues';
        }

        // Vérification rapide de la syntaxe des sections principales
        $sections = $this->identifySections($content);
        if ($sections === []) {
            $warnings[] = 'No main sections found in YAML';
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Retourne les métriques de performance
     */
    public function getPerformanceMetrics(): array
    {
        $metrics = $this->performanceMetrics;

        // Calculer des statistiques dérivées
        $totalParses = $metrics['total_parses'];
        if ($totalParses > 0) {
            $metrics['cache_hit_rate'] = round(($metrics['cache_hits'] / $totalParses) * 100, 2);
            $metrics['lazy_load_rate'] = round(($metrics['lazy_loads'] / $totalParses) * 100, 2);
            $metrics['streaming_rate'] = round(($metrics['streaming_parses'] / $totalParses) * 100, 2);
        }

        // Ajouter des informations sur le cache persistant
        $metrics['persistent_cache_enabled'] = $this->cacheService->isEnabled();
        $metrics['persistent_cache_stats'] = $this->cacheService->getStats();

        return $metrics;
    }

    /**
     * Remet à zéro les métriques de performance
     */
    public function resetMetrics(): void
    {
        $this->performanceMetrics = [
            'total_parses' => 0,
            'cache_hits' => 0,
            'cache_misses' => 0,
            'lazy_loads' => 0,
            'streaming_parses' => 0,
            'memory_saved_bytes' => 0,
            'time_saved_ms' => 0,
        ];
    }

    /**
     * Nettoie les caches pour libérer la mémoire
     */
    public function clearCache(): void
    {
        $this->sectionCache = [];

        gc_collect_cycles();
    }

    /**
     * Détermine la stratégie de parsing optimale basée sur la taille et le contenu
     */
    private function determineParsingStrategy(string $content, array $options): array
    {
        $contentSize = mb_strlen($content);
        $contentHash = hash('xxh3', $content);

        // Vérifier le cache d'abord
        if ($this->isCached($contentHash)) {
            $this->performanceMetrics['cache_hits']++;

            return [
                'data' => $this->getCachedResult($contentHash),
                'strategy' => 'cached',
                'from_cache' => true,
            ];
        }

        $this->performanceMetrics['cache_misses']++;

        // Stratégie basée sur la taille
        if ($contentSize > self::STREAMING_THRESHOLD) {
            return $this->streamingParse($content, $contentHash);
        }
        if ($contentSize > self::LARGE_FILE_THRESHOLD) {
            return $this->lazyParse($content, $contentHash, $options);
        }

        return $this->standardParse($content, $contentHash);

    }

    /**
     * Parsing standard pour les petits fichiers
     */
    private function standardParse(string $content, string $hash): array
    {
        $parser = new Parser();
        $data = $parser->parse($content, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);

        $this->cacheResult($hash, $data);

        return [
            'data' => $data,
            'strategy' => 'standard',
            'from_cache' => false,
        ];
    }

    /**
     * Parsing paresseux pour les fichiers moyens - ne parse que les sections demandées
     */
    private function lazyParse(string $content, string $hash, array $options): array
    {
        $this->performanceMetrics['lazy_loads']++;

        // Identifier les sections principales rapidement
        $sections = $this->identifySections($content);
        $requestedSections = $options['sections'] ?? ['core']; // Par défaut, ne parser que 'core'

        $result = [];
        $parser = new Parser();

        foreach ($requestedSections as $sectionName) {
            if (isset($sections[$sectionName])) {
                $sectionContent = $this->extractSection($content, $sections[$sectionName]);
                $result[$sectionName] = $parser->parse($sectionContent, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
            }
        }

        // Si aucune section spécifique demandée, parser tout mais en chunks
        if (empty($requestedSections) || in_array('*', $requestedSections)) {
            $result = $this->parseInChunks($content, $parser);
        }

        $this->cacheResult($hash, $result);

        return [
            'data' => $result,
            'strategy' => 'lazy',
            'from_cache' => false,
        ];
    }

    /**
     * Parsing en streaming pour les très gros fichiers
     */
    private function streamingParse(string $content, string $hash): array
    {
        $this->performanceMetrics['streaming_parses']++;

        // Pour les très gros fichiers, utiliser une approche ligne par ligne
        $lines = explode("\n", $content);
        $result = [];
        $currentSection = null;
        $currentSectionContent = [];
        $parser = new Parser();

        foreach ($lines as $lineNumber => $line) {
            // Détecter les nouvelles sections
            if (preg_match('/^(\w+):\s*$/', mb_trim($line), $matches)) {
                // Sauvegarder la section précédente
                if ($currentSection !== null && $currentSectionContent !== []) {
                    $sectionYaml = implode("\n", $currentSectionContent);
                    try {
                        $result[$currentSection] = $parser->parse($sectionYaml, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
                    } catch (ParseException $e) {
                        // Log l'erreur mais continue
                        $this->logger->logWarning(
                            'Section parsing failed in streaming mode',
                            ['section' => $currentSection, 'line' => $lineNumber, 'error' => $e->getMessage()],
                            'Skipping problematic section'
                        );
                    }
                }

                $currentSection = $matches[1];
                $currentSectionContent = [$line];
            } elseif ($currentSection !== null) {
                $currentSectionContent[] = $line;
            }

            // Libérer la mémoire périodiquement
            if ($lineNumber % 1000 === 0) {
                gc_collect_cycles();
            }
        }

        // Traiter la dernière section
        if ($currentSection !== null && $currentSectionContent !== []) {
            $sectionYaml = implode("\n", $currentSectionContent);
            try {
                $result[$currentSection] = $parser->parse($sectionYaml, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
            } catch (ParseException $e) {
                $this->logger->logWarning(
                    'Final section parsing failed in streaming mode',
                    ['section' => $currentSection, 'error' => $e->getMessage()],
                    'Skipping problematic final section'
                );
            }
        }

        $this->cacheResult($hash, $result);

        return [
            'data' => $result,
            'strategy' => 'streaming',
            'from_cache' => false,
        ];
    }

    /**
     * Identifie rapidement les sections principales du YAML sans parsing complet
     */
    private function identifySections(string $content): array
    {
        $sections = [];
        $lines = explode("\n", $content);

        foreach ($lines as $lineNumber => $line) {
            if (preg_match('/^(\w+):\s*$/', mb_trim($line), $matches)) {
                $sections[$matches[1]] = $lineNumber;
            }
        }

        return $sections;
    }

    /**
     * Extrait une section spécifique du contenu YAML
     */
    private function extractSection(string $content, int $startLine): string
    {
        $lines = explode("\n", $content);
        $sectionLines = [];
        $inSection = false;
        $sectionIndent = null;
        $counter = count($lines);

        for ($i = $startLine; $i < $counter; $i++) {
            $line = $lines[$i];

            if ($i === $startLine) {
                $sectionLines[] = $line;
                $inSection = true;

                continue;
            }

            // Déterminer l'indentation de la section
            if ($sectionIndent === null && mb_trim($line) !== '') {
                $sectionIndent = mb_strlen($line) - mb_strlen(mb_ltrim($line));
            }

            // Vérifier si on est toujours dans la section
            if (mb_trim($line) !== '' && in_array(preg_match('/^\s/', $line), [0, false], true) && $inSection) {
                // Nouvelle section de niveau racine trouvée
                break;
            }

            $sectionLines[] = $line;
        }

        return implode("\n", $sectionLines);
    }

    /**
     * Parse le contenu en chunks pour optimiser la mémoire
     */
    private function parseInChunks(string $content, Parser $parser): array
    {
        $chunkSize = 100 * 1024; // 100KB par chunk
        $contentLength = mb_strlen($content);
        $result = [];

        if ($contentLength <= $chunkSize) {
            return $parser->parse($content, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
        }

        // Pour les gros fichiers, essayer de parser en entier avec gestion mémoire
        $originalMemoryLimit = ini_get('memory_limit');
        $requiredMemory = $contentLength * 3; // Estimation: 3x la taille du fichier

        if ($this->increaseMemoryIfNeeded($requiredMemory)) {
            try {
                $result = $parser->parse($content, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
                $this->performanceMetrics['memory_saved_bytes'] += $requiredMemory;
            } finally {
                ini_set('memory_limit', $originalMemoryLimit);
            }
        }

        return $result;
    }

    /**
     * Augmente la limite mémoire si nécessaire et possible
     */
    private function increaseMemoryIfNeeded(int $requiredBytes): bool
    {
        $currentLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
        $currentUsage = memory_get_usage(true);
        $available = $currentLimit - $currentUsage;

        if ($available < $requiredBytes) {
            $newLimit = $currentUsage + $requiredBytes + (50 * 1024 * 1024); // +50MB de marge
            $newLimitFormatted = number_format($newLimit / 1024 / 1024, 0).'M';

            if (ini_set('memory_limit', $newLimitFormatted) !== false) {
                $this->logger->logWarning(
                    'Memory limit increased for YAML parsing',
                    [
                        'old_limit' => ini_get('memory_limit'),
                        'new_limit' => $newLimitFormatted,
                        'required_bytes' => $requiredBytes,
                    ],
                    'Consider optimizing YAML structure for better memory usage'
                );

                return true;
            }
        }

        return false;
    }

    /**
     * Parse une limite mémoire PHP en octets
     */
    private function parseMemoryLimit(string $limit): int
    {
        $limit = mb_trim($limit);
        $unit = mb_strtolower(mb_substr($limit, -1));
        $value = (int) mb_substr($limit, 0, -1);

        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => (int) $limit,
        };
    }

    /**
     * Vérifie si un résultat est en cache
     */
    private function isCached(string $hash): bool
    {
        return isset($this->sectionCache[$hash]);
    }

    /**
     * Récupère un résultat du cache
     */
    private function getCachedResult(string $hash): array
    {
        if (isset($this->sectionCache[$hash])) {
            return $this->sectionCache[$hash];
        }

        throw new RuntimeException("Cache miss for hash {$hash}");
    }

    /**
     * Met en cache un résultat de parsing
     */
    private function cacheResult(string $hash, array $data): void
    {
        $this->sectionCache[$hash] = $data;

        // Limiter la taille du cache en mémoire
        if (count($this->sectionCache) > 100) {
            // Garder seulement les 50 entrées les plus récentes
            $this->sectionCache = array_slice($this->sectionCache, -50, null, true);
        }
    }

    /**
     * Vérifie l'indentation du YAML
     */
    private function checkIndentation(array $lines): array
    {
        $warnings = [];
        $indentLevels = [];

        foreach ($lines as $lineNumber => $line) {
            if (mb_trim($line) === '' || str_starts_with(mb_trim($line), '#')) {
                continue; // Ignorer les lignes vides et commentaires
            }

            $indent = mb_strlen($line) - mb_strlen(mb_ltrim($line));
            if ($indent > 0) {
                $indentLevels[] = $indent;
            }

            // Vérifier les tabulations
            if (str_contains($line, "\t")) {
                $warnings[] = 'Line '.($lineNumber + 1).' contains tabs - use spaces for YAML indentation';
            }
        }

        // Vérifier la cohérence de l'indentation
        if ($indentLevels !== []) {
            $uniqueIndents = array_unique($indentLevels);
            $gcd = $this->findGCD($uniqueIndents);

            if ($gcd > 4) {
                $warnings[] = "Large indentation detected ({$gcd} spaces) - consider using 2 or 4 spaces";
            } elseif ($gcd === 1) {
                $warnings[] = 'Inconsistent indentation detected - use consistent spacing (2 or 4 spaces)';
            }
        }

        return $warnings;
    }

    /**
     * Trouve le PGCD d'un tableau de nombres
     */
    private function findGCD(array $numbers): int
    {
        if ($numbers === []) {
            return 1;
        }

        $gcd = array_shift($numbers);
        foreach ($numbers as $number) {
            $gcd = $this->gcd($gcd, $number);
        }

        return $gcd;
    }

    /**
     * Calcule le PGCD de deux nombres
     */
    private function gcd(int $a, int $b): int
    {
        while ($b !== 0) {
            $temp = $b;
            $b = $a % $b;
            $a = $temp;
        }

        return $a;
    }
}
