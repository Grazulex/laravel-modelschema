<?php

declare(strict_types=1);

/**
 * Trait-Based Plugin System Example
 *
 * Demonstrates the modern trait-based approach to creating and using
 * field type plugins in Laravel ModelSchema.
 */

namespace Grazulex\LaravelModelschema\Examples;

use Grazulex\LaravelModelschema\Support\FieldTypePlugin;
use Grazulex\LaravelModelschema\Support\FieldTypePluginManager;

/**
 * Example: Advanced File Upload Field with Trait-Based Configuration
 */
class FileUploadFieldTypePlugin extends FieldTypePlugin
{
    protected string $version = '2.0.0';
    protected string $author = 'Laravel ModelSchema Team';
    protected string $description = 'Advanced file upload field with trait-based validation and storage options';

    public function __construct()
    {
        // Define modular traits for file upload configuration
        $this->customAttributes = [
            'allowed_extensions',
            'max_file_size',
            'storage_disk',
            'storage_path',
            'auto_optimize',
            'generate_thumbnails',
            'virus_scan',
            'metadata_extraction',
            'compression_level',
            'encryption_enabled'
        ];

        // Configure each trait with sophisticated validation and behavior
        $this->customAttributeConfig = [
            'allowed_extensions' => [
                'type' => 'array',
                'required' => false,
                'default' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'],
                'validator' => function ($extensions): array {
                    $errors = [];
                    foreach ($extensions as $ext) {
                        if (!is_string($ext) || !preg_match('/^[a-zA-Z0-9]+$/', $ext)) {
                            $errors[] = "Invalid extension format: {$ext}";
                        }
                    }
                    return $errors;
                },
                'description' => 'File extensions allowed for upload'
            ],
            
            'max_file_size' => [
                'type' => 'string',
                'required' => false,
                'default' => '10MB',
                'validator' => function ($size): array {
                    if (!preg_match('/^\d+[KMGT]?B$/i', $size)) {
                        return ['max_file_size must be in format like "10MB", "500KB", "2GB"'];
                    }
                    return [];
                },
                'transform' => function ($size) {
                    // Convert human-readable size to bytes
                    return $this->convertSizeToBytes($size);
                },
                'description' => 'Maximum file size (e.g., "10MB", "500KB")'
            ],

            'storage_disk' => [
                'type' => 'string',
                'required' => false,
                'default' => 'local',
                'enum' => ['local', 'public', 's3', 'gcs', 'azure'],
                'description' => 'Storage disk for uploaded files'
            ],

            'storage_path' => [
                'type' => 'string',
                'required' => false,
                'default' => 'uploads',
                'validator' => function ($path): array {
                    if (str_contains($path, '..')) {
                        return ['storage_path cannot contain ".." for security reasons'];
                    }
                    return [];
                },
                'transform' => function ($path) {
                    return trim($path, '/'); // Normalize path
                },
                'description' => 'Storage path within the disk'
            ],

            'auto_optimize' => [
                'type' => 'boolean',
                'required' => false,
                'default' => true,
                'description' => 'Automatically optimize uploaded images'
            ],

            'generate_thumbnails' => [
                'type' => 'array',
                'required' => false,
                'default' => [],
                'validator' => function ($sizes): array {
                    $errors = [];
                    foreach ($sizes as $name => $dimensions) {
                        if (!is_string($name) || !preg_match('/^\d+x\d+$/', $dimensions)) {
                            $errors[] = "Invalid thumbnail size format: {$name} => {$dimensions}";
                        }
                    }
                    return $errors;
                },
                'description' => 'Thumbnail sizes to generate (e.g., ["small" => "150x150", "medium" => "300x300"])'
            ],

            'virus_scan' => [
                'type' => 'boolean',
                'required' => false,
                'default' => false,
                'description' => 'Enable virus scanning for uploaded files'
            ],

            'metadata_extraction' => [
                'type' => 'boolean',
                'required' => false,
                'default' => false,
                'description' => 'Extract and store file metadata (EXIF, etc.)'
            ],

            'compression_level' => [
                'type' => 'integer',
                'required' => false,
                'min' => 0,
                'max' => 100,
                'default' => 85,
                'description' => 'Image compression level (0-100, higher = better quality)'
            ],

            'encryption_enabled' => [
                'type' => 'boolean',
                'required' => false,
                'default' => false,
                'description' => 'Encrypt files at rest'
            ]
        ];
    }

    public function getType(): string
    {
        return 'file_upload';
    }

    public function getAliases(): array
    {
        return ['file', 'upload', 'attachment', 'media'];
    }

    public function validate(array $config): array
    {
        $errors = [];

        // Cross-trait validation: virus scan requires specific storage
        if (($config['virus_scan'] ?? false) && !in_array($config['storage_disk'] ?? 'local', ['local', 's3'])) {
            $errors[] = 'Virus scanning requires local or s3 storage disk';
        }

        // Cross-trait validation: thumbnails only for images
        if (!empty($config['generate_thumbnails'])) {
            $allowedExtensions = $config['allowed_extensions'] ?? $this->customAttributeConfig['allowed_extensions']['default'];
            $imageExtensions = array_intersect($allowedExtensions, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
            if (empty($imageExtensions)) {
                $errors[] = 'Thumbnail generation requires image extensions in allowed_extensions';
            }
        }

        return $errors;
    }

    public function getCastType(array $config): ?string
    {
        return 'array'; // Store file metadata as JSON
    }

    public function getValidationRules(array $config): array
    {
        $rules = ['file'];

        // Build validation rules from traits
        if (isset($config['allowed_extensions'])) {
            $extensions = implode(',', $config['allowed_extensions']);
            $rules[] = "mimes:{$extensions}";
        }

        if (isset($config['max_file_size_bytes'])) {
            $maxKb = ceil($config['max_file_size_bytes'] / 1024);
            $rules[] = "max:{$maxKb}";
        }

        return $rules;
    }

    public function getMigrationParameters(array $config): array
    {
        return [
            'type' => 'json',
            'nullable' => $config['nullable'] ?? true
        ];
    }

    public function transformConfig(array $config): array
    {
        // Apply trait transformations
        $processed = $this->processCustomAttributes($config);

        // Additional business logic transformations
        if ($processed['encryption_enabled'] && !isset($processed['storage_disk'])) {
            $processed['storage_disk'] = 'local'; // Force local for encryption
        }

        return $processed;
    }

    public function getMigrationCall(array $config): string
    {
        $nullable = ($config['nullable'] ?? true) ? '->nullable()' : '';
        return "json('{$config['name']}'){$nullable}";
    }

    public function supportsAttribute(string $attribute): bool
    {
        $standardAttributes = ['nullable', 'default'];
        return in_array($attribute, array_merge($standardAttributes, $this->customAttributes), true);
    }

    /**
     * Helper method for size conversion trait
     */
    private function convertSizeToBytes(string $size): int
    {
        $units = ['B' => 1, 'KB' => 1024, 'MB' => 1048576, 'GB' => 1073741824, 'TB' => 1099511627776];
        
        if (preg_match('/^(\d+)([KMGT]?B)$/i', $size, $matches)) {
            $number = (int) $matches[1];
            $unit = strtoupper($matches[2]);
            return $number * ($units[$unit] ?? 1);
        }
        
        return 0;
    }
}

/**
 * Example: Geographic Coordinates Field with Validation Traits
 */
class GeographicCoordinatesFieldTypePlugin extends FieldTypePlugin
{
    protected string $version = '1.0.0';
    protected string $author = 'Laravel ModelSchema Team';
    protected string $description = 'Geographic coordinates field with validation and formatting traits';

    public function __construct()
    {
        $this->customAttributes = [
            'coordinate_system',
            'precision',
            'auto_format',
            'validate_bounds',
            'default_country',
            'require_elevation'
        ];

        $this->customAttributeConfig = [
            'coordinate_system' => [
                'type' => 'string',
                'required' => false,
                'default' => 'WGS84',
                'enum' => ['WGS84', 'NAD83', 'UTM', 'State Plane'],
                'description' => 'Geographic coordinate system'
            ],

            'precision' => [
                'type' => 'integer',
                'required' => false,
                'min' => 1,
                'max' => 15,
                'default' => 6,
                'description' => 'Decimal precision for coordinates'
            ],

            'auto_format' => [
                'type' => 'boolean',
                'required' => false,
                'default' => true,
                'description' => 'Automatically format coordinates on save'
            ],

            'validate_bounds' => [
                'type' => 'array',
                'required' => false,
                'default' => ['latitude' => [-90, 90], 'longitude' => [-180, 180]],
                'validator' => function ($bounds): array {
                    if (!isset($bounds['latitude']) || !isset($bounds['longitude'])) {
                        return ['validate_bounds must contain latitude and longitude arrays'];
                    }
                    return [];
                },
                'description' => 'Coordinate bounds for validation'
            ],

            'default_country' => [
                'type' => 'string',
                'required' => false,
                'validator' => function ($country): array {
                    if (!preg_match('/^[A-Z]{2}$/', $country)) {
                        return ['default_country must be a 2-letter ISO country code'];
                    }
                    return [];
                },
                'description' => 'Default country code for validation context'
            ],

            'require_elevation' => [
                'type' => 'boolean',
                'required' => false,
                'default' => false,
                'description' => 'Require elevation data with coordinates'
            ]
        ];
    }

    public function getType(): string
    {
        return 'coordinates';
    }

    public function getAliases(): array
    {
        return ['geo', 'location', 'position', 'latlng'];
    }

    public function validate(array $config): array
    {
        return []; // Custom validation logic would go here
    }

    public function getCastType(array $config): ?string
    {
        return 'array';
    }

    public function getValidationRules(array $config): array
    {
        return ['array', 'array:latitude,longitude'];
    }

    public function getMigrationParameters(array $config): array
    {
        return ['type' => 'json'];
    }

    public function transformConfig(array $config): array
    {
        return $this->processCustomAttributes($config);
    }

    public function getMigrationCall(array $config): string
    {
        return "json('{$config['name']}')";
    }

    public function supportsAttribute(string $attribute): bool
    {
        return in_array($attribute, array_merge(['nullable', 'default'], $this->customAttributes), true);
    }
}

/**
 * Demo class showing trait-based plugin usage
 */
class TraitBasedPluginExample
{
    private FieldTypePluginManager $pluginManager;

    public function __construct()
    {
        $this->pluginManager = new FieldTypePluginManager();
    }

    /**
     * Demonstrate plugin registration and trait configuration
     */
    public function demonstrateTraitBasedPlugins(): array
    {
        // Register trait-based plugins
        $fileUploadPlugin = new FileUploadFieldTypePlugin();
        $coordinatesPlugin = new GeographicCoordinatesFieldTypePlugin();

        $this->pluginManager->registerPlugin($fileUploadPlugin);
        $this->pluginManager->registerPlugin($coordinatesPlugin);

        // Test file upload configuration with traits
        $fileConfig = [
            'allowed_extensions' => ['jpg', 'png', 'pdf'],
            'max_file_size' => '5MB',
            'storage_disk' => 's3',
            'auto_optimize' => true,
            'generate_thumbnails' => [
                'small' => '150x150',
                'medium' => '300x300'
            ],
            'virus_scan' => true
        ];

        // Process traits - applies defaults, transformations, and validation
        $processedFileConfig = $fileUploadPlugin->processCustomAttributes($fileConfig);

        // Test coordinates configuration with traits
        $coordsConfig = [
            'coordinate_system' => 'WGS84',
            'precision' => 8,
            'validate_bounds' => [
                'latitude' => [45.0, 50.0],   // Limited to specific region
                'longitude' => [2.0, 8.0]
            ],
            'default_country' => 'FR'
        ];

        $processedCoordsConfig = $coordinatesPlugin->processCustomAttributes($coordsConfig);

        return [
            'file_upload' => [
                'original' => $fileConfig,
                'processed' => $processedFileConfig,
                'validation_rules' => $fileUploadPlugin->getValidationRules($processedFileConfig),
                'migration_call' => $fileUploadPlugin->getMigrationCall($processedFileConfig)
            ],
            'coordinates' => [
                'original' => $coordsConfig,
                'processed' => $processedCoordsConfig,
                'validation_rules' => $coordinatesPlugin->getValidationRules($processedCoordsConfig),
                'migration_call' => $coordinatesPlugin->getMigrationCall($processedCoordsConfig)
            ]
        ];
    }

    /**
     * Demonstrate trait validation and error handling
     */
    public function demonstrateTraitValidation(): array
    {
        $fileUploadPlugin = new FileUploadFieldTypePlugin();

        $testCases = [
            'valid_config' => [
                'allowed_extensions' => ['jpg', 'png'],
                'max_file_size' => '2MB',
                'storage_disk' => 'local'
            ],
            'invalid_extensions' => [
                'allowed_extensions' => ['jpg', 'invalid-ext!'], // Invalid format
                'max_file_size' => '2MB'
            ],
            'invalid_size_format' => [
                'allowed_extensions' => ['jpg'],
                'max_file_size' => 'not-a-size' // Invalid format
            ],
            'cross_trait_conflict' => [
                'virus_scan' => true,
                'storage_disk' => 'gcs' // Virus scan not supported on GCS
            ]
        ];

        $results = [];
        foreach ($testCases as $caseName => $config) {
            $errors = $fileUploadPlugin->validate($config);
            
            // Also test individual trait validation
            $traitErrors = [];
            foreach ($fileUploadPlugin->getCustomAttributes() as $attribute) {
                if (isset($config[$attribute])) {
                    $attrErrors = $fileUploadPlugin->validateCustomAttribute($attribute, $config[$attribute]);
                    $traitErrors[$attribute] = $attrErrors;
                }
            }

            $results[$caseName] = [
                'config' => $config,
                'validation_errors' => $errors,
                'trait_errors' => array_filter($traitErrors),
                'is_valid' => empty($errors) && empty(array_filter($traitErrors))
            ];
        }

        return $results;
    }

    /**
     * Show how traits enable modular configuration
     */
    public function demonstrateTraitModularity(): array
    {
        $plugin = new FileUploadFieldTypePlugin();

        // Different configurations using the same traits
        $configurations = [
            'basic_images' => [
                'allowed_extensions' => ['jpg', 'png'],
                'max_file_size' => '2MB'
            ],
            'secure_documents' => [
                'allowed_extensions' => ['pdf', 'doc', 'docx'],
                'max_file_size' => '10MB',
                'virus_scan' => true,
                'encryption_enabled' => true
            ],
            'media_gallery' => [
                'allowed_extensions' => ['jpg', 'png', 'gif'],
                'max_file_size' => '5MB',
                'auto_optimize' => true,
                'generate_thumbnails' => [
                    'thumb' => '100x100',
                    'small' => '300x300',
                    'medium' => '600x600'
                ]
            ]
        ];

        $results = [];
        foreach ($configurations as $name => $config) {
            $processed = $plugin->processCustomAttributes($config);
            $results[$name] = [
                'input_traits' => array_keys($config),
                'processed_config' => $processed,
                'applied_defaults' => array_diff_key($processed, $config),
                'validation_rules' => $plugin->getValidationRules($processed)
            ];
        }

        return $results;
    }
}