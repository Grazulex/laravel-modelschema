<?php

declare(strict_types=1);

/**
 * Exemple d'utilisation des améliorations du RequestGenerator avec Form Requests personnalisées
 *
 * Ce fichier montre comment utiliser les nouvelles fonctionnalités du RequestGenerator
 * pour générer des Form Requests Laravel avec des fonctionnalités avancées.
 */

use Grazulex\LaravelModelschema\Schema\Field;
use Grazulex\LaravelModelschema\Schema\ModelSchema;
use Grazulex\LaravelModelschema\Schema\Relationship;
use Grazulex\LaravelModelschema\Services\Generation\Generators\RequestGenerator;

// Créer un schéma d'exemple
$fields = [
    'title' => new Field(
        name: 'title',
        type: 'string',
        nullable: false,
        validation: ['required' => true, 'max' => 255]
    ),
    'content' => new Field(
        name: 'content',
        type: 'text',
        nullable: false,
        validation: ['required' => true]
    ),
    'status' => new Field(
        name: 'status',
        type: 'enum',
        nullable: false,
        attributes: ['options' => ['draft', 'published', 'archived']],
        validation: ['required' => true]
    ),
    'user_id' => new Field(
        name: 'user_id',
        type: 'integer',
        nullable: false,
        validation: ['required' => true, 'exists' => 'users,id']
    ),
];

$relationships = [
    'user' => new Relationship('user', 'belongsTo', 'User'),
    'tags' => new Relationship('tags', 'belongsToMany', 'Tag'),
];

$schema = new ModelSchema('Post', 'posts', $fields, $relationships);

// Créer le générateur
$generator = new RequestGenerator();

// === EXEMPLE 1: Form Requests basiques avec structure améliorée ===
echo "=== FORM REQUESTS BASIQUES AMÉLIORÉES ===\n";

$basicOptions = [
    'enhanced' => true,
    'enable_authorization' => true,
    'enable_custom_messages' => true,
    'requests_namespace' => 'App\\Http\\Requests\\Blog',
];

$basicResult = $generator->generate($schema, $basicOptions);
$basicData = json_decode($basicResult['json'], true);

echo 'Nombre de types de requests générées: '.count($basicData['requests'])."\n";
echo 'Types: '.implode(', ', array_keys($basicData['requests']))."\n";

// Afficher la structure d'une request
$storeRequest = $basicData['requests']['store'];
echo "\nStructure Store Request:\n";
echo "- Nom: {$storeRequest['name']}\n";
echo "- Namespace: {$storeRequest['namespace']}\n";
echo '- Autorisation activée: '.($storeRequest['authorization']['enabled'] ? 'Oui' : 'Non')."\n";
echo '- Nombre de méthodes personnalisées: '.count($storeRequest['custom_methods'])."\n";
echo '- Règles de validation: '.count($storeRequest['validation_rules'])."\n";

// === EXEMPLE 2: Form Requests avec autorisation désactivée ===
echo "\n=== FORM REQUESTS AVEC AUTORISATION DÉSACTIVÉE ===\n";

$noAuthOptions = [
    'enhanced' => true,
    'enable_authorization' => false,
];

$noAuthResult = $generator->generate($schema, $noAuthOptions);
$noAuthData = json_decode($noAuthResult['json'], true);

$noAuthStore = $noAuthData['requests']['store'];
echo "Logique d'autorisation: ".implode(' ', $noAuthStore['authorization']['logic'])."\n";

// === EXEMPLE 3: Form Requests personnalisées ===
echo "\n=== FORM REQUESTS PERSONNALISÉES ===\n";

$customOptions = [
    'enhanced' => true,
    'enable_authorization' => true,
    'requests_namespace' => 'App\\Http\\Requests\\Blog',
    'custom_requests' => [
        'publish' => [
            'class_name' => 'PublishPostRequest',
            'namespace' => 'App\\Http\\Requests\\Blog\\Actions',
            'validation_rules' => [
                'scheduled_at' => ['nullable', 'date', 'after:now'],
                'notify_subscribers' => ['boolean'],
            ],
        ],
        'bulk_update' => [
            'class_name' => 'BulkUpdatePostsRequest',
            'validation_rules' => [
                'post_ids' => ['required', 'array'],
                'post_ids.*' => ['integer', 'exists:posts,id'],
                'action' => ['required', 'in:publish,archive,delete'],
            ],
        ],
    ],
];

$customResult = $generator->generate($schema, $customOptions);
$customData = json_decode($customResult['json'], true);

echo "Requests personnalisées générées:\n";
foreach ($customData['requests'] as $key => $request) {
    echo "- {$key}: {$request['name']} ({$request['namespace']})\n";
}

// === EXEMPLE 4: Mode traditionnel (rétrocompatibilité) ===
echo "\n=== MODE TRADITIONNEL (RÉTROCOMPATIBILITÉ) ===\n";

$traditionalOptions = [
    'enhanced' => false,
    'requests_namespace' => 'App\\Http\\Requests',
];

$traditionalResult = $generator->generate($schema, $traditionalOptions);
$traditionalData = json_decode($traditionalResult['json'], true);

echo "Structure traditionnelle:\n";
foreach ($traditionalData['requests'] as $key => $request) {
    echo "- {$key}: {$request['name']}\n";
    echo '  Champs disponibles: '.implode(', ', array_keys($request))."\n";
}

// === EXEMPLE 5: Génération YAML ===
echo "\n=== GÉNÉRATION YAML ===\n";

$yamlOptions = [
    'enhanced' => true,
    'enable_authorization' => true,
];

$yamlResult = $generator->generate($schema, $yamlOptions);
echo "Extrait YAML (premières lignes):\n";
echo implode("\n", array_slice(explode("\n", $yamlResult['yaml']), 0, 10))."\n...\n";

// === EXEMPLE 6: Analyse des fonctionnalités avancées ===
echo "\n=== ANALYSE DES FONCTIONNALITÉS AVANCÉES ===\n";

$advancedResult = $generator->generate($schema, ['enhanced' => true, 'enable_authorization' => true]);
$advancedData = json_decode($advancedResult['json'], true);
$storeRequest = $advancedData['requests']['store'];

echo "Messages de validation personnalisés:\n";
foreach ($storeRequest['messages'] as $rule => $message) {
    echo "- {$rule}: {$message}\n";
}

echo "\nValidation des relations:\n";
foreach ($storeRequest['relationships_validation'] as $field => $rules) {
    echo "- {$field}: ".(is_array($rules) ? implode(', ', $rules) : $rules)."\n";
}

echo "\nRègles conditionnelles:\n";
foreach ($storeRequest['conditional_rules'] as $field => $condition) {
    echo "- {$field}: {$condition['method']} avec condition dynamique\n";
}

echo "\n=== UTILISATION DANS UNE APPLICATION ===\n";
echo "Pour utiliser ces fragments dans votre application:\n\n";
echo "1. Récupérez les données JSON/YAML générées\n";
echo "2. Parsez le contenu avec json_decode() ou Yaml::parse()\n";
echo "3. Utilisez les données pour générer vos fichiers PHP de Form Requests\n";
echo "4. Intégrez les règles de validation, autorisation et méthodes personnalisées\n\n";

echo "Exemple d'intégration:\n";
echo '```php'."\n";
echo '$requestData = json_decode($result["json"], true);'."\n";
echo '$storeRequest = $requestData["requests"]["store"];'."\n";
echo '// Générer le fichier StorePostRequest.php avec les données'."\n";
echo '$className = $storeRequest["name"];'."\n";
echo '$namespace = $storeRequest["namespace"];'."\n";
echo '$rules = $storeRequest["validation_rules"];'."\n";
echo '$authorization = $storeRequest["authorization"]["logic"];'."\n";
echo '```'."\n";

echo "\n✅ Les améliorations du RequestGenerator fournissent:\n";
echo "- Support des Form Requests personnalisées\n";
echo "- Autorisation configurable par action\n";
echo "- Messages de validation spécifiques aux champs\n";
echo "- Validation automatique des relations\n";
echo "- Règles conditionnelles pour logique complexe\n";
echo "- Méthodes personnalisées (prepareForValidation, etc.)\n";
echo "- Rétrocompatibilité avec mode traditionnel\n";
echo "- Support complet JSON et YAML\n";
