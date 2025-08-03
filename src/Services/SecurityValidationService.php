<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\Services;

/**
 * Service de validation sécuritaire pour protéger contre les injections de code
 * et valider les entrées utilisateur dans les schémas et fragments.
 */
class SecurityValidationService
{
    /**
     * Patterns dangereux pour l'injection de code PHP
     */
    private const DANGEROUS_PHP_PATTERNS = [
        '/\<\?php/',           // Ouverture PHP
        '/\?\>/',              // Fermeture PHP
        '/eval\s*\(/',         // eval()
        '/exec\s*\(/',         // exec()
        '/system\s*\(/',       // system()
        '/passthru\s*\(/',     // passthru()
        '/shell_exec\s*\(/',   // shell_exec()
        '/file_get_contents\s*\(/', // file_get_contents()
        '/file_put_contents\s*\(/', // file_put_contents()
        '/fopen\s*\(/',        // fopen()
        '/fwrite\s*\(/',       // fwrite()
        '/include\s*\(/',      // include()
        '/require\s*\(/',      // require()
        '/include_once\s*\(/', // include_once()
        '/require_once\s*\(/', // require_once()
        '/\$\$/',              // Variable variables
        '/\${/',               // Variable parsing
        '/__halt_compiler\s*\(/', // __halt_compiler()
    ];

    /**
     * Patterns dangereux pour l'injection SQL
     */
    private const DANGEROUS_SQL_PATTERNS = [
        '/;\s*(DROP|DELETE|UPDATE|INSERT|CREATE|ALTER)\s+/i',
        '/UNION\s+SELECT/i',
        '/\'\s*OR\s+\'/i',
        '/\'\s*AND\s+\'/i',
        '/--\s*/',
        '/\/\*.*?\*\//',
        '/\bINTO\s+OUTFILE\b/i',
        '/\bINTO\s+DUMPFILE\b/i',
    ];

    /**
     * Caractères dangereux pour les noms de classe/namespace
     */
    private const DANGEROUS_CHARS = ['\\', '/', '<', '>', '?', ':', '*', '|', '"', "'", '`'];

    /**
     * Extensions de fichiers autorisées pour les stubs
     */
    private const ALLOWED_STUB_EXTENSIONS = ['stub', 'yaml', 'yml', 'json'];

    /**
     * Valide un nom de classe selon les standards PHP et sécurité
     */
    public function validateClassName(string $className): array
    {
        $errors = [];
        $warnings = [];

        // Vérifier que le nom n'est pas vide
        if ($className === '' || $className === '0') {
            $errors[] = 'Class name cannot be empty';

            return ['errors' => $errors, 'warnings' => $warnings];
        }

        // Vérifier la longueur
        if (mb_strlen($className) > 100) {
            $errors[] = 'Class name is too long (max 100 characters)';
        }

        // Vérifier les caractères dangereux
        foreach (self::DANGEROUS_CHARS as $char) {
            if (str_contains($className, $char)) {
                $errors[] = "Class name contains dangerous character: '{$char}'";
            }
        }

        // Vérifier le format PHP valide
        if (in_array(preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $className), [0, false], true)) {
            $errors[] = 'Class name must be a valid PHP identifier';
        }

        // Vérifier les mots réservés PHP
        $reservedWords = [
            'abstract', 'and', 'array', 'as', 'break', 'callable', 'case', 'catch', 'class',
            'clone', 'const', 'continue', 'declare', 'default', 'die', 'do', 'echo', 'else',
            'elseif', 'empty', 'enddeclare', 'endfor', 'endforeach', 'endif', 'endswitch',
            'endwhile', 'eval', 'exit', 'extends', 'final', 'finally', 'for', 'foreach',
            'function', 'global', 'goto', 'if', 'implements', 'include', 'include_once',
            'instanceof', 'insteadof', 'interface', 'isset', 'list', 'namespace', 'new',
            'or', 'print', 'private', 'protected', 'public', 'require', 'require_once',
            'return', 'static', 'switch', 'throw', 'trait', 'try', 'unset', 'use', 'var',
            'while', 'xor', 'yield',
        ];

        if (in_array(mb_strtolower($className), $reservedWords)) {
            $errors[] = "Class name '{$className}' is a reserved PHP keyword";
        }

        // Vérifications de sécurité spécifiques
        if (preg_match('/\d+$/', $className)) {
            $warnings[] = 'Class name ending with numbers might be problematic';
        }

        if (str_starts_with($className, '__')) {
            $warnings[] = 'Class name starting with double underscore is reserved for PHP magic methods';
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Valide un namespace selon les standards PHP et sécurité
     */
    public function validateNamespace(string $namespace): array
    {
        $errors = [];
        $warnings = [];

        if ($namespace === '' || $namespace === '0') {
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        // Vérifier la longueur
        if (mb_strlen($namespace) > 255) {
            $errors[] = 'Namespace is too long (max 255 characters)';
        }

        // Vérifier les caractères dangereux (sauf backslash qui est valide)
        $dangerousChars = array_diff(self::DANGEROUS_CHARS, ['\\']);
        foreach ($dangerousChars as $char) {
            if (str_contains($namespace, $char)) {
                $errors[] = "Namespace contains dangerous character: '{$char}'";
            }
        }

        // Séparer les parties du namespace
        $parts = explode('\\', $namespace);
        foreach ($parts as $part) {
            if ($part === '' || $part === '0') {
                continue; // Permet les backslashes au début
            }

            $partValidation = $this->validateClassName($part);
            if (! empty($partValidation['errors'])) {
                $errors[] = "Invalid namespace part '{$part}': ".implode(', ', $partValidation['errors']);
            }
        }

        // Vérifier les patterns suspects
        if (preg_match('/\\\\{3,}/', $namespace)) {
            $errors[] = 'Namespace contains excessive backslashes';
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Vérifie et sanitise le contenu pour prévenir l'injection de code
     */
    public function sanitizeContent(string $content): array
    {
        $errors = [];
        $warnings = [];
        $sanitized = $content;

        // Vérifier les patterns PHP dangereux
        foreach (self::DANGEROUS_PHP_PATTERNS as $pattern) {
            if (preg_match($pattern, $content)) {
                $errors[] = "Content contains dangerous PHP pattern: {$pattern}";
            }
        }

        // Vérifier les patterns SQL dangereux
        foreach (self::DANGEROUS_SQL_PATTERNS as $pattern) {
            if (preg_match($pattern, $content)) {
                $warnings[] = "Content contains potential SQL injection pattern: {$pattern}";
            }
        }

        // Vérifier les caractères de contrôle dangereux
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $content)) {
            $warnings[] = 'Content contains control characters';
        }

        // Vérifier les tentatives d'évasion (mais pas les nouvelles lignes YAML normales)
        $contentWithoutYamlNewlines = str_replace('\n', '', $content);
        if (preg_match('/\\\\[rt\'\"\\\\]/', $contentWithoutYamlNewlines)) {
            $warnings[] = 'Content contains escape sequences';
        }

        // Sanitiser les caractères dangereux (sans altérer la fonctionnalité)
        $sanitized = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $sanitized);

        return [
            'errors' => $errors,
            'warnings' => $warnings,
            'sanitized' => $sanitized,
            'is_safe' => $errors === [],
        ];
    }

    /**
     * Valide un chemin de fichier stub
     */
    public function validateStubPath(string $stubPath): array
    {
        $errors = [];
        $warnings = [];

        // Vérifier que le chemin n'est pas vide
        if ($stubPath === '' || $stubPath === '0') {
            $errors[] = 'Stub path cannot be empty';

            return ['errors' => $errors, 'warnings' => $warnings];
        }

        // Normaliser le chemin
        $normalizedPath = realpath($stubPath) ?: $stubPath;

        // Vérifier les tentatives de path traversal
        if (str_contains($stubPath, '../') || str_contains($stubPath, '..\\')) {
            $errors[] = 'Stub path contains path traversal attempts';
        }

        // Vérifier les caractères dangereux
        $dangerousPathChars = ['<', '>', ':', '*', '?', '"', '|'];
        foreach ($dangerousPathChars as $char) {
            if (str_contains($stubPath, $char)) {
                $errors[] = "Stub path contains dangerous character: '{$char}'";
            }
        }

        // Vérifier l'extension du fichier
        $extension = pathinfo($stubPath, PATHINFO_EXTENSION);
        if (! in_array(mb_strtolower($extension), self::ALLOWED_STUB_EXTENSIONS)) {
            $errors[] = "Stub file extension '{$extension}' is not allowed. Allowed: ".implode(', ', self::ALLOWED_STUB_EXTENSIONS);
        }

        // Vérifier la longueur du chemin
        if (mb_strlen($stubPath) > 260) { // Limite Windows
            $warnings[] = 'Stub path is very long and might cause issues on some systems';
        }

        // Vérifier si le fichier existe et est lisible
        if (file_exists($normalizedPath)) {
            if (! is_readable($normalizedPath)) {
                $errors[] = 'Stub file is not readable';
            }
            if (! is_file($normalizedPath)) {
                $errors[] = 'Stub path is not a file';
            }
        } else {
            $warnings[] = 'Stub file does not exist';
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings,
            'normalized_path' => $normalizedPath,
        ];
    }

    /**
     * Effectue un audit de sécurité complet sur un contenu de stub
     */
    public function auditStubContent(string $stubContent): array
    {
        $errors = [];
        $warnings = [];
        $recommendations = [];

        // Audit de base du contenu
        $contentAudit = $this->sanitizeContent($stubContent);
        $errors = array_merge($errors, $contentAudit['errors']);
        $warnings = array_merge($warnings, $contentAudit['warnings']);

        // Vérifier les placeholders suspects
        if (preg_match('/\{\{\s*\$\w+\s*\}\}/', $stubContent)) {
            $warnings[] = 'Stub contains variable-like placeholders that might be vulnerable';
        }

        // Vérifier les commentaires suspects
        if (preg_match('/\/\*.*?(TODO|FIXME|HACK|XXX).*?\*\//is', $stubContent)) {
            $warnings[] = 'Stub contains TODO/FIXME comments that might indicate incomplete security measures';
        }

        // Vérifier les URLs ou chemins absolus
        if (preg_match('/https?:\/\/|file:\/\/|ftp:\/\//', $stubContent)) {
            $warnings[] = 'Stub contains external URLs that might pose security risks';
        }

        // Vérifier les expressions régulières complexes
        if (preg_match('/\/.*[.*+?^${}()|[\]\\\\].*\/[gimxs]*/', $stubContent)) {
            $warnings[] = 'Stub contains complex regular expressions that should be reviewed';
        }

        // Recommandations de sécurité
        if ($errors === [] && $warnings === []) {
            $recommendations[] = 'Stub content appears to be secure';
        } else {
            $recommendations[] = 'Review and fix security issues before using this stub';
        }

        if (mb_strlen($stubContent) > 10000) {
            $recommendations[] = 'Consider splitting large stubs into smaller, more manageable pieces';
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings,
            'recommendations' => $recommendations,
            'is_secure' => $errors === [],
            'security_score' => $this->calculateSecurityScore($errors, $warnings),
        ];
    }

    /**
     * Valide une valeur de configuration pour éviter l'injection
     */
    public function validateConfigValue(string $key, mixed $value): array
    {
        $errors = [];
        $warnings = [];

        // Convertir en string pour validation
        is_string($value) ? $value : json_encode($value);

        // Vérifier les clés de configuration sensibles
        $sensitiveKeys = ['database', 'password', 'secret', 'key', 'token', 'api'];
        foreach ($sensitiveKeys as $sensitiveKey) {
            if (str_contains(mb_strtolower($key), $sensitiveKey)) {
                $warnings[] = "Configuration key '{$key}' appears to contain sensitive information";
                break;
            }
        }

        // Valider la valeur
        if (is_string($value)) {
            $contentValidation = $this->sanitizeContent($value);
            $errors = array_merge($errors, $contentValidation['errors']);
            $warnings = array_merge($warnings, $contentValidation['warnings']);
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Valide un schéma complet pour les problèmes de sécurité
     */
    public function validateSchemaContent(array $schemaData): array
    {
        $errors = [];
        $warnings = [];

        // Valider récursivement toutes les valeurs string dans le schéma
        $this->validateSchemaRecursive($schemaData, $errors, $warnings);

        // Valider les noms de modèles et tables
        if (isset($schemaData['core']['model'])) {
            $modelValidation = $this->validateClassName($schemaData['core']['model']);
            $errors = array_merge($errors, $modelValidation['errors']);
            $warnings = array_merge($warnings, $modelValidation['warnings']);
        }

        if (isset($schemaData['core']['namespace'])) {
            $namespaceValidation = $this->validateNamespace($schemaData['core']['namespace']);
            $errors = array_merge($errors, $namespaceValidation['errors']);
            $warnings = array_merge($warnings, $namespaceValidation['warnings']);
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings,
            'is_secure' => $errors === [],
            'security_score' => $this->calculateSecurityScore($errors, $warnings),
        ];
    }

    /**
     * Calcule un score de sécurité basé sur les erreurs et warnings
     */
    private function calculateSecurityScore(array $errors, array $warnings): int
    {
        $score = 100;
        $score -= count($errors) * 20; // -20 points par erreur
        $score -= count($warnings) * 5; // -5 points par warning

        return max(0, $score);
    }

    /**
     * Valide récursivement les données du schéma
     */
    private function validateSchemaRecursive(mixed $data, array &$errors, array &$warnings, string $path = ''): void
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $currentPath = $path !== '' && $path !== '0' ? "{$path}.{$key}" : $key;

                // Valider la clé si c'est une string
                if (is_string($key)) {
                    $keyValidation = $this->sanitizeContent($key);
                    if (! empty($keyValidation['errors'])) {
                        $errors[] = "Security issue in schema key at '{$currentPath}': ".implode(', ', $keyValidation['errors']);
                    }
                }

                $this->validateSchemaRecursive($value, $errors, $warnings, $currentPath);
            }
        } elseif (is_string($data)) {
            $contentValidation = $this->sanitizeContent($data);
            if (! empty($contentValidation['errors'])) {
                $errors[] = "Security issue in schema value at '{$path}': ".implode(', ', $contentValidation['errors']);
            }
            if (! empty($contentValidation['warnings'])) {
                $warnings[] = "Security warning in schema value at '{$path}': ".implode(', ', $contentValidation['warnings']);
            }
        }
    }
}
