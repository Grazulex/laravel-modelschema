<?php

declare(strict_types=1);

namespace JeanMarcStrauven\LaravelModelschema\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Service de logging détaillé pour le package ModelSchema
 * 
 * Fournit des logs structurés et informatifs pour le debugging
 * et le monitoring des opérations de génération de schéma.
 */
class LoggingService
{
    private const LOG_CHANNEL = 'modelschema';
    
    private bool $enabled = true;
    private string $sessionId;
    private array $contextStack = [];
    private float $startTime;
    
    public function __construct()
    {
        $this->sessionId = Str::random(8);
        $this->startTime = microtime(true);
        $this->enabled = config('modelschema.logging.enabled', true);
    }
    
    /**
     * Log une opération de début avec contexte
     */
    public function logOperationStart(string $operation, array $context = []): void
    {
        if (!$this->enabled) {
            return;
        }
        
        $this->pushContext($operation, $context);
        
        Log::channel($this->getLogChannel())->info("🚀 Starting {$operation}", [
            'session_id' => $this->sessionId,
            'operation' => $operation,
            'context' => $context,
            'timestamp' => now()->toISOString(),
            'memory_usage' => $this->formatBytes(memory_get_usage()),
            'context_depth' => count($this->contextStack)
        ]);
    }
    
    /**
     * Log la fin d'une opération avec métriques
     */
    public function logOperationEnd(string $operation, array $metrics = []): void
    {
        if (!$this->enabled) {
            return;
        }
        
        $context = $this->popContext($operation);
        $duration = microtime(true) - ($context['start_time'] ?? $this->startTime);
        
        Log::channel($this->getLogChannel())->info("✅ Completed {$operation}", [
            'session_id' => $this->sessionId,
            'operation' => $operation,
            'duration_ms' => round($duration * 1000, 2),
            'memory_usage' => $this->formatBytes(memory_get_usage()),
            'memory_peak' => $this->formatBytes(memory_get_peak_usage()),
            'metrics' => $metrics,
            'context_depth' => count($this->contextStack)
        ]);
    }
    
    /**
     * Log des informations de debugging détaillées
     */
    public function logDebug(string $message, array $data = []): void
    {
        if (!$this->enabled) {
            return;
        }
        
        Log::channel($this->getLogChannel())->debug("🔍 {$message}", [
            'session_id' => $this->sessionId,
            'data' => $data,
            'context_stack' => array_column($this->contextStack, 'operation'),
            'memory_usage' => $this->formatBytes(memory_get_usage())
        ]);
    }
    
    /**
     * Log des avertissements avec recommandations
     */
    public function logWarning(string $message, array $context = [], ?string $recommendation = null): void
    {
        if (!$this->enabled) {
            return;
        }
        
        $logData = [
            'session_id' => $this->sessionId,
            'context' => $context,
            'current_operation' => $this->getCurrentOperation(),
            'memory_usage' => $this->formatBytes(memory_get_usage())
        ];
        
        if ($recommendation) {
            $logData['recommendation'] = $recommendation;
        }
        
        Log::channel($this->getLogChannel())->warning("⚠️ {$message}", $logData);
    }
    
    /**
     * Log des erreurs avec contexte détaillé
     */
    public function logError(string $message, ?\Throwable $exception = null, array $context = []): void
    {
        if (!$this->enabled) {
            return;
        }
        
        $logData = [
            'session_id' => $this->sessionId,
            'context' => $context,
            'context_stack' => array_column($this->contextStack, 'operation'),
            'current_operation' => $this->getCurrentOperation(),
            'memory_usage' => $this->formatBytes(memory_get_usage()),
            'timestamp' => now()->toISOString()
        ];
        
        if ($exception) {
            $logData['exception'] = [
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ];
        }
        
        Log::channel($this->getLogChannel())->error("❌ {$message}", $logData);
    }
    
    /**
     * Log des métriques de performance
     */
    public function logPerformance(string $operation, array $metrics): void
    {
        if (!$this->enabled) {
            return;
        }
        
        Log::channel($this->getLogChannel())->info("📊 Performance: {$operation}", [
            'session_id' => $this->sessionId,
            'operation' => $operation,
            'metrics' => $metrics,
            'memory_usage' => $this->formatBytes(memory_get_usage()),
            'memory_peak' => $this->formatBytes(memory_get_peak_usage()),
            'session_duration_ms' => round((microtime(true) - $this->startTime) * 1000, 2)
        ]);
    }
    
    /**
     * Log de validation avec détails des erreurs
     */
    public function logValidation(string $type, bool $success, array $errors = [], array $warnings = [], array $stats = []): void
    {
        if (!$this->enabled) {
            return;
        }
        
        $level = $success ? 'info' : 'error';
        $emoji = $success ? '✅' : '❌';
        
        Log::channel($this->getLogChannel())->log($level, "{$emoji} Validation {$type}: " . ($success ? 'Passed' : 'Failed'), [
            'session_id' => $this->sessionId,
            'validation_type' => $type,
            'success' => $success,
            'error_count' => count($errors),
            'warning_count' => count($warnings),
            'errors' => $errors,
            'warnings' => $warnings,
            'statistics' => $stats,
            'current_operation' => $this->getCurrentOperation()
        ]);
    }
    
    /**
     * Log de génération de fichier/fragment
     */
    public function logGeneration(string $type, string $target, bool $success, array $stats = []): void
    {
        if (!$this->enabled) {
            return;
        }
        
        $emoji = $success ? '🎯' : '💥';
        $message = "{$emoji} Generation {$type} -> {$target}: " . ($success ? 'Success' : 'Failed');
        
        Log::channel($this->getLogChannel())->info($message, [
            'session_id' => $this->sessionId,
            'generation_type' => $type,
            'target' => $target,
            'success' => $success,
            'statistics' => $stats,
            'current_operation' => $this->getCurrentOperation(),
            'memory_usage' => $this->formatBytes(memory_get_usage())
        ]);
    }
    
    /**
     * Log de parsing de fichier YAML
     */
    public function logYamlParsing(string $source, bool $success, array $stats = [], array $errors = []): void
    {
        if (!$this->enabled) {
            return;
        }
        
        $emoji = $success ? '📄' : '💥';
        $message = "{$emoji} YAML Parsing: {$source}";
        
        Log::channel($this->getLogChannel())->info($message, [
            'session_id' => $this->sessionId,
            'source' => $source,
            'success' => $success,
            'statistics' => $stats,
            'errors' => $errors,
            'current_operation' => $this->getCurrentOperation(),
            'memory_usage' => $this->formatBytes(memory_get_usage())
        ]);
    }
    
    /**
     * Log d'opération de cache
     */
    public function logCache(string $operation, string $key, bool $hit = false, ?float $duration = null): void
    {
        if (!$this->enabled) {
            return;
        }
        
        $emoji = match($operation) {
            'hit' => '🎯',
            'miss' => '⚡',
            'store' => '💾',
            'clear' => '🧹',
            default => '📦'
        };
        
        $logData = [
            'session_id' => $this->sessionId,
            'cache_operation' => $operation,
            'cache_key' => $key,
            'cache_hit' => $hit,
            'current_operation' => $this->getCurrentOperation()
        ];
        
        if ($duration !== null) {
            $logData['duration_ms'] = round($duration * 1000, 2);
        }
        
        Log::channel($this->getLogChannel())->debug("{$emoji} Cache {$operation}: {$key}", $logData);
    }
    
    /**
     * Active ou désactive le logging
     */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }
    
    /**
     * Vérifie si le logging est activé
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
    
    /**
     * Récupère l'ID de session actuel
     */
    public function getSessionId(): string
    {
        return $this->sessionId;
    }
    
    /**
     * Récupère le canal de log configuré
     */
    private function getLogChannel(): string
    {
        return config('modelschema.logging.channel', self::LOG_CHANNEL);
    }
    
    /**
     * Ajoute un contexte à la pile
     */
    private function pushContext(string $operation, array $context): void
    {
        $this->contextStack[] = [
            'operation' => $operation,
            'context' => $context,
            'start_time' => microtime(true)
        ];
    }
    
    /**
     * Retire un contexte de la pile
     */
    private function popContext(string $operation): array
    {
        for ($i = count($this->contextStack) - 1; $i >= 0; $i--) {
            if ($this->contextStack[$i]['operation'] === $operation) {
                return array_splice($this->contextStack, $i, 1)[0];
            }
        }
        
        return ['operation' => $operation, 'start_time' => microtime(true)];
    }
    
    /**
     * Récupère l'opération courante
     */
    private function getCurrentOperation(): ?string
    {
        return empty($this->contextStack) ? null : end($this->contextStack)['operation'];
    }
    
    /**
     * Formate les bytes en unité lisible
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
