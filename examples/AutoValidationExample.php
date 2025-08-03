<?php

declare(strict_types=1);

/**
 * Example demonstrating the AutoValidationService for generating Laravel validation rules
 * automatically from schema definitions and custom field attributes.
 */

require_once 'vendor/autoload.php';

use Grazulex\LaravelModelschema\Examples\JsonSchemaFieldTypePlugin;
use Grazulex\LaravelModelschema\Examples\UrlFieldTypePlugin;
use Grazulex\LaravelModelschema\Services\AutoValidationService;
use Grazulex\LaravelModelschema\Services\SchemaService;
use Grazulex\LaravelModelschema\Support\FieldTypePluginManager;

echo "=== Laravel ModelSchema Auto Validation Examples ===\n\n";

// Initialize services
$pluginManager = new FieldTypePluginManager();
$autoValidator = new AutoValidationService($pluginManager);
$schemaService = new SchemaService(autoValidator: $autoValidator);

// Register custom field type plugins
$pluginManager->registerPlugin(new UrlFieldTypePlugin());
$pluginManager->registerPlugin(new JsonSchemaFieldTypePlugin());

echo 'Registered field type plugins: '.implode(', ', array_keys($pluginManager->getPlugins()))."\n\n";

// Example 1: Basic User Schema with Standard Fields
echo "=== Example 1: User Schema with Standard Field Types ===\n";

$userYaml = <<<'YAML'
model: User
table: users
fields:
  name:
    type: string
    nullable: false
    attributes:
      length: 255
  email:
    type: email
    nullable: false
    attributes:
      unique: true
      length: 255
  email_verified_at:
    type: timestamp
    nullable: true
  password:
    type: string
    nullable: false
    attributes:
      length: 255
  age:
    type: integer
    nullable: true
    attributes:
      min: 0
      max: 150
  is_active:
    type: boolean
    nullable: false
    default: true
  created_at:
    type: timestamp
    nullable: false
  updated_at:
    type: timestamp
    nullable: true
YAML;

try {
    $validationRules = $schemaService->generateValidationRulesFromYaml($userYaml);
    $validationMessages = $schemaService->generateValidationMessages(
        $schemaService->parseYamlContent($userYaml)
    );

    echo "Generated Validation Rules:\n";
    foreach ($validationRules as $field => $rules) {
        echo "  {$field}: ".implode('|', $rules)."\n";
    }

    echo "\nGenerated Validation Messages:\n";
    foreach ($validationMessages as $key => $message) {
        echo "  {$key}: {$message}\n";
    }

} catch (Exception $e) {
    echo 'Error: '.$e->getMessage()."\n";
}

echo "\n".str_repeat('=', 80)."\n\n";

// Example 2: E-commerce Product Schema with Complex Fields
echo "=== Example 2: Product Schema with Complex Field Types ===\n";

$productYaml = <<<'YAML'
model: Product
table: products
fields:
  name:
    type: string
    nullable: false
    attributes:
      length: 255
  slug:
    type: string
    nullable: false
    attributes:
      unique: true
      length: 255
  description:
    type: text
    nullable: true
  price:
    type: decimal
    nullable: false
    attributes:
      precision: 8
      scale: 2
  weight:
    type: float
    nullable: true
  quantity:
    type: integer
    nullable: false
    default: 0
  status:
    type: enum
    nullable: false
    attributes:
      values: [active, inactive, discontinued]
    default: active
  tags:
    type: set
    nullable: true
    attributes:
      values: [featured, sale, new, bestseller]
  category_id:
    type: foreignId
    nullable: false
    attributes:
      references:
        table: categories
        column: id
  supplier_id:
    type: foreignId
    nullable: true
  metadata:
    type: json
    nullable: true
  published_at:
    type: dateTime
    nullable: true
  created_at:
    type: timestamp
    nullable: false
  updated_at:
    type: timestamp
    nullable: true
YAML;

try {
    $schema = $schemaService->parseYamlContent($productYaml);
    $validationConfig = $schemaService->generateValidationConfig($schema);

    echo "Complete Validation Configuration:\n\n";

    echo "Rules:\n";
    foreach ($validationConfig['rules'] as $field => $rules) {
        echo "  '{$field}' => '".implode('|', $rules)."',\n";
    }

    echo "\nMessages:\n";
    foreach ($validationConfig['messages'] as $key => $message) {
        echo "  '{$key}' => '{$message}',\n";
    }

    echo "\nAttribute Names:\n";
    foreach ($validationConfig['attributes'] as $field => $displayName) {
        echo "  '{$field}' => '{$displayName}',\n";
    }

} catch (Exception $e) {
    echo 'Error: '.$e->getMessage()."\n";
}

echo "\n".str_repeat('=', 80)."\n\n";

// Example 3: Custom Field Types with Plugins
echo "=== Example 3: Schema with Custom Field Type Plugins ===\n";

$customYaml = <<<'YAML'
model: Website
table: websites
fields:
  name:
    type: string
    nullable: false
    attributes:
      length: 255
  url:
    type: url  # Custom field type with UrlFieldTypePlugin
    nullable: false
  api_config:
    type: json_schema  # Custom field type with JsonSchemaFieldTypePlugin
    nullable: true
  location:
    type: point  # Spatial field type
    nullable: true
  geo_boundary:
    type: polygon  # Spatial field type
    nullable: true
  metadata:
    type: json
    nullable: true
  is_secure:
    type: boolean
    nullable: false
    default: true
  created_at:
    type: timestamp
    nullable: false
  updated_at:
    type: timestamp
    nullable: true
YAML;

try {
    $schema = $schemaService->parseYamlContent($customYaml);
    $validationRules = $schemaService->generateValidationRules($schema);

    echo "Validation Rules with Custom Field Types:\n";
    foreach ($validationRules as $field => $rules) {
        echo "  {$field}: ".implode('|', $rules)."\n";

        // Show custom validation rules for special field types
        $customRules = $autoValidator->generateCustomValidationRules($schema->getAllFields()[$field]);
        if (! empty($customRules)) {
            echo '    Custom rules: '.implode('|', $customRules)."\n";
        }
    }

    echo "\nLaravel Request Format:\n";
    $requestFormat = $schemaService->generateValidationRulesForRequest($schema, 'string');
    echo $requestFormat."\n";

} catch (Exception $e) {
    echo 'Error: '.$e->getMessage()."\n";
}

echo "\n".str_repeat('=', 80)."\n\n";

// Example 4: Realistic Blog Post Schema
echo "=== Example 4: Blog Post Schema with Relations ===\n";

$blogYaml = <<<'YAML'
model: BlogPost
table: blog_posts
fields:
  title:
    type: string
    nullable: false
    attributes:
      length: 255
  slug:
    type: string
    nullable: false
    attributes:
      unique: true
      length: 255
  excerpt:
    type: text
    nullable: true
  content:
    type: longText
    nullable: false
  featured_image:
    type: string
    nullable: true
    attributes:
      length: 500
  author_id:
    type: foreignId
    nullable: false
    attributes:
      references:
        table: users
        column: id
  category_id:
    type: foreignId
    nullable: false
    attributes:
      references:
        table: categories
        column: id
  status:
    type: enum
    nullable: false
    attributes:
      values: [draft, published, archived]
    default: draft
  views_count:
    type: unsignedBigInteger
    nullable: false
    default: 0
  reading_time:
    type: smallInteger
    nullable: true
  is_featured:
    type: boolean
    nullable: false
    default: false
  seo_title:
    type: string
    nullable: true
    attributes:
      length: 60
  seo_description:
    type: string
    nullable: true
    attributes:
      length: 160
  published_at:
    type: dateTime
    nullable: true
  created_at:
    type: timestamp
    nullable: false
  updated_at:
    type: timestamp
    nullable: true

relationships:
  author:
    type: belongsTo
    model: User
    foreign_key: author_id
  category:
    type: belongsTo
    model: Category
    foreign_key: category_id
  tags:
    type: belongsToMany
    model: Tag
    pivot_table: blog_post_tags
  comments:
    type: hasMany
    model: Comment
    foreign_key: blog_post_id
YAML;

try {
    $schema = $schemaService->parseYamlContent($blogYaml);
    $validationConfig = $schemaService->generateValidationConfig($schema);

    echo "Blog Post Validation Rules:\n";
    foreach ($validationConfig['rules'] as $field => $rules) {
        echo sprintf("  %-20s => %s\n", "'{$field}'", "'".implode('|', $rules)."'");
    }

    echo "\nExample Laravel FormRequest:\n";
    echo "<?php\n\n";
    echo "class StoreBlogPostRequest extends FormRequest\n";
    echo "{\n";
    echo "    public function rules(): array\n";
    echo "    {\n";
    echo "        return [\n";
    foreach ($validationConfig['rules'] as $field => $rules) {
        echo "            '{$field}' => '".implode('|', $rules)."',\n";
    }
    echo "        ];\n";
    echo "    }\n\n";
    echo "    public function messages(): array\n";
    echo "    {\n";
    echo "        return [\n";
    foreach (array_slice($validationConfig['messages'], 0, 5) as $key => $message) {
        echo "            '{$key}' => '{$message}',\n";
    }
    if (count($validationConfig['messages']) > 5) {
        echo '            // ... '.(count($validationConfig['messages']) - 5)." more messages\n";
    }
    echo "        ];\n";
    echo "    }\n";
    echo "}\n";

} catch (Exception $e) {
    echo 'Error: '.$e->getMessage()."\n";
}

echo "\n".str_repeat('=', 80)."\n\n";

// Example 5: Integration with Custom Attributes
echo "=== Example 5: Custom Attributes Integration ===\n";

echo "Note: This example shows how custom attributes from field type plugins\n";
echo "automatically contribute to validation rule generation.\n\n";

echo "UrlFieldTypePlugin custom attributes:\n";
$urlPlugin = new UrlFieldTypePlugin();
foreach ($urlPlugin->getCustomAttributes() as $attr) {
    $config = $urlPlugin->getCustomAttributeConfig($attr);
    echo "  - {$attr}: {$config['description']}\n";
}

echo "\nJsonSchemaFieldTypePlugin custom attributes:\n";
$jsonPlugin = new JsonSchemaFieldTypePlugin();
foreach ($jsonPlugin->getCustomAttributes() as $attr) {
    $config = $jsonPlugin->getCustomAttributeConfig($attr);
    echo "  - {$attr}: {$config['description']}\n";
}

echo "\nThese custom attributes are automatically processed to generate\n";
echo "appropriate Laravel validation rules, providing seamless integration\n";
echo "between schema definitions and Laravel validation.\n";

echo "\n=== Auto Validation Examples Complete ===\n";
