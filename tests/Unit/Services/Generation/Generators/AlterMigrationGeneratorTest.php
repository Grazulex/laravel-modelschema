<?php

declare(strict_types=1);

use Grazulex\LaravelModelschema\Schema\Field;
use Grazulex\LaravelModelschema\Schema\ModelSchema;
use Grazulex\LaravelModelschema\Schema\Relationship;
use Grazulex\LaravelModelschema\Services\Generation\GenerationService;
use Grazulex\LaravelModelschema\Services\Generation\Generators\AlterMigrationGenerator;

describe('AlterMigrationGenerator', function () {
    beforeEach(function () {
        $this->generator = new AlterMigrationGenerator();
    });

    test('exposes its name and formats', function () {
        expect($this->generator->getGeneratorName())->toBe('alter_migration');
        expect($this->generator->getAvailableFormats())->toBe(['json', 'yaml', 'php']);
    });

    test('reports no migration when schemas are identical', function () {
        $schema = new ModelSchema('User', 'users', ['name' => new Field('name', 'string')]);

        $data = $this->generator->buildMigrationData($schema, $schema);

        expect($data['requires_migration'])->toBeFalse();
        expect($data['operations'])->toBe([]);
        expect($data['up'])->toBe([]);
        expect($data['down'])->toBe([]);

        $result = $this->generator->generate($schema, $schema);
        expect($result['php'])->toContain('//');
        expect($result['php'])->not->toContain('Schema::table');
    });

    test('adds a column and drops it on rollback', function () {
        $old = new ModelSchema('User', 'users', ['name' => new Field('name', 'string')]);
        $new = new ModelSchema('User', 'users', [
            'name' => new Field('name', 'string'),
            'age' => new Field('age', 'integer', nullable: true),
        ]);

        $data = $this->generator->buildMigrationData($old, $new);

        expect($data['requires_migration'])->toBeTrue();
        expect($data['operations'])->toHaveCount(1);
        expect($data['operations'][0]['type'])->toBe('add_column');
        expect($data['up'])->toBe(["\$table->integer('age')->nullable();"]);
        expect($data['down'])->toBe(["\$table->dropColumn('age');"]);
        expect($data['warnings'])->toBe([]);
    });

    test('warns when a NOT NULL column without default is added', function () {
        $old = new ModelSchema('User', 'users', []);
        $new = new ModelSchema('User', 'users', ['status' => new Field('status', 'string')]);

        $data = $this->generator->buildMigrationData($old, $new);

        expect($data['warnings'])->toHaveCount(1);
        expect($data['warnings'][0])->toContain("Column 'status' is added as NOT NULL");
    });

    test('adds unique and index constraints as separate operations', function () {
        $old = new ModelSchema('User', 'users', []);
        $new = new ModelSchema('User', 'users', [
            'email' => new Field('email', 'email', unique: true, default: ''),
            'code' => new Field('code', 'string', nullable: true, index: true, length: 20),
        ]);

        $data = $this->generator->buildMigrationData($old, $new);

        expect($data['up'])->toBe([
            "\$table->string('email')->default('');",
            "\$table->string('code', 20)->nullable();",
            "\$table->unique('email');",
            "\$table->index('code');",
        ]);
        expect($data['down'])->toBe([
            "\$table->dropIndex(['code']);",
            "\$table->dropUnique(['email']);",
            "\$table->dropColumn('code');",
            "\$table->dropColumn('email');",
        ]);
    });

    test('drops a column and its unique index, restoring both on rollback', function () {
        $old = new ModelSchema('User', 'users', [
            'name' => new Field('name', 'string'),
            'email' => new Field('email', 'string', unique: true),
        ]);
        $new = new ModelSchema('User', 'users', ['name' => new Field('name', 'string')]);

        $data = $this->generator->buildMigrationData($old, $new);

        expect($data['up'])->toBe([
            "\$table->dropUnique(['email']);",
            "\$table->dropColumn('email');",
        ]);
        expect($data['down'])->toBe([
            "\$table->string('email');",
            "\$table->unique('email');",
        ]);
        expect($data['data_loss_risk'])->toBe('high');
        expect($data['breaking_changes'])->not->toBeEmpty();
        expect($data['warnings'])->toContain("Dropping column 'email' permanently deletes its data.");
    });

    test('modifies a column with change() and restores the previous definition', function () {
        $old = new ModelSchema('User', 'users', ['name' => new Field('name', 'string', length: 100)]);
        $new = new ModelSchema('User', 'users', [
            'name' => new Field('name', 'string', nullable: true, length: 255, comment: "User's name"),
        ]);

        $data = $this->generator->buildMigrationData($old, $new);

        expect($data['operations'])->toHaveCount(1);
        expect($data['operations'][0]['type'])->toBe('modify_column');
        expect(array_keys($data['operations'][0]['changes']))->toBe(['nullable', 'length', 'comment']);
        expect($data['up'])->toBe(["\$table->string('name', 255)->nullable()->comment('User\\'s name')->change();"]);
        expect($data['down'])->toBe(["\$table->string('name', 100)->change();"]);
    });

    test('warns about type changes and columns becoming required', function () {
        $old = new ModelSchema('Product', 'products', [
            'price' => new Field('price', 'string', nullable: true),
        ]);
        $new = new ModelSchema('Product', 'products', [
            'price' => new Field('price', 'decimal', precision: 10, scale: 2, default: 0),
        ]);

        $data = $this->generator->buildMigrationData($old, $new);

        expect($data['up'])->toBe(["\$table->decimal('price', 10, 2)->default(0)->change();"]);
        expect($data['down'])->toBe(["\$table->string('price')->nullable()->change();"]);
        expect($data['warnings'])->toHaveCount(2);
        expect($data['data_loss_risk'])->toBe('medium');
    });

    test('adds and drops indexes on existing columns', function () {
        $old = new ModelSchema('User', 'users', [
            'email' => new Field('email', 'string', unique: true),
            'city' => new Field('city', 'string'),
        ]);
        $new = new ModelSchema('User', 'users', [
            'email' => new Field('email', 'string'),
            'city' => new Field('city', 'string', index: true),
        ]);

        $data = $this->generator->buildMigrationData($old, $new);

        expect($data['up'])->toBe([
            "\$table->dropUnique(['email']);",
            "\$table->index('city');",
        ]);
        expect($data['down'])->toBe([
            "\$table->dropIndex(['city']);",
            "\$table->unique('email');",
        ]);
    });

    test('renames columns declared in the renames option', function () {
        $old = new ModelSchema('User', 'users', ['full_name' => new Field('full_name', 'string')]);
        $new = new ModelSchema('User', 'users', ['name' => new Field('name', 'string', nullable: true)]);

        $data = $this->generator->buildMigrationData($old, $new, ['renames' => ['full_name' => 'name']]);

        expect(array_column($data['operations'], 'type'))->toBe(['rename_column', 'modify_column']);
        expect($data['up'])->toBe([
            "\$table->renameColumn('full_name', 'name');",
            "\$table->string('name')->nullable()->change();",
        ]);
        expect($data['down'])->toBe([
            "\$table->string('name')->change();",
            "\$table->renameColumn('name', 'full_name');",
        ]);
        expect($data['warnings'])->toBe([]);
    });

    test('drops the old index before renaming and adds the new one after', function () {
        $old = new ModelSchema('User', 'users', ['mail' => new Field('mail', 'string', unique: true)]);
        $new = new ModelSchema('User', 'users', ['email' => new Field('email', 'string', index: true)]);

        $data = $this->generator->buildMigrationData($old, $new, ['renames' => ['mail' => 'email']]);

        expect($data['up'])->toBe([
            "\$table->dropUnique(['mail']);",
            "\$table->renameColumn('mail', 'email');",
            "\$table->index('email');",
        ]);
        expect($data['down'])->toBe([
            "\$table->dropIndex(['email']);",
            "\$table->renameColumn('email', 'mail');",
            "\$table->unique('mail');",
        ]);
    });

    test('ignores invalid renames with a warning', function () {
        $old = new ModelSchema('User', 'users', ['full_name' => new Field('full_name', 'string', nullable: true)]);
        $new = new ModelSchema('User', 'users', ['name' => new Field('name', 'string', nullable: true)]);

        $data = $this->generator->buildMigrationData($old, $new, ['renames' => ['unknown' => 'name']]);

        expect(array_column($data['operations'], 'type'))->toBe(['drop_column', 'add_column']);
        expect($data['warnings'][0])->toContain("Rename 'unknown' => 'name' ignored");
    });

    test('adds and drops foreign keys for belongsTo relationships', function () {
        $old = new ModelSchema('Post', 'posts', ['title' => new Field('title', 'string')], [
            'category' => new Relationship('category', 'belongsTo', 'App\\Models\\Category'),
        ]);
        $new = new ModelSchema('Post', 'posts', ['title' => new Field('title', 'string')], [
            'author' => new Relationship('author', 'belongsTo', 'App\\Models\\User'),
        ]);

        $data = $this->generator->buildMigrationData($old, $new);

        expect(array_column($data['operations'], 'type'))->toBe([
            'drop_foreign', 'drop_column', 'add_column', 'add_foreign',
        ]);
        expect($data['up'])->toBe([
            "\$table->dropForeign(['category_id']);",
            "\$table->dropColumn('category_id');",
            "\$table->integer('author_id')->nullable()->comment('Foreign key for author relationship');",
            "\$table->foreign('author_id')->references('id')->on('users');",
        ]);
        expect($data['down'])->toBe([
            "\$table->dropForeign(['author_id']);",
            "\$table->dropColumn('author_id');",
            "\$table->integer('category_id')->nullable()->comment('Foreign key for category relationship');",
            "\$table->foreign('category_id')->references('id')->on('categories');",
        ]);
    });

    test('handles timestamps and soft deletes options', function () {
        $old = new ModelSchema('User', 'users', [], [], ['timestamps' => true, 'soft_deletes' => false]);
        $new = new ModelSchema('User', 'users', [], [], ['timestamps' => false, 'soft_deletes' => true]);

        $data = $this->generator->buildMigrationData($old, $new);

        expect($data['up'])->toBe(['$table->dropTimestamps();', '$table->softDeletes();']);
        expect($data['down'])->toBe(['$table->dropSoftDeletes();', '$table->timestamps();']);
    });

    test('renders enum values', function () {
        $old = new ModelSchema('Post', 'posts', [
            'status' => new Field('status', 'enum', attributes: ['values' => ['draft', 'published']], default: 'draft'),
        ]);
        $new = new ModelSchema('Post', 'posts', [
            'status' => new Field('status', 'enum', attributes: ['values' => ['draft', 'published', 'archived']], default: 'draft'),
        ]);

        $data = $this->generator->buildMigrationData($old, $new);

        expect($data['up'])->toBe(["\$table->enum('status', ['draft', 'published', 'archived'])->default('draft')->change();"]);
        expect($data['down'])->toBe(["\$table->enum('status', ['draft', 'published'])->default('draft')->change();"]);
    });

    test('renders a complete migration file with a table rename', function () {
        $old = new ModelSchema('User', 'users', ['name' => new Field('name', 'string')]);
        $new = new ModelSchema('Member', 'members', [
            'name' => new Field('name', 'string'),
            'bio' => new Field('bio', 'text', nullable: true),
        ]);

        $result = $this->generator->generate($old, $new);
        $php = $result['php'];

        expect($result['metadata']['generator'])->toBe('alter_migration');
        expect($result['metadata']['old_table_name'])->toBe('users');
        expect($result['metadata']['table_name'])->toBe('members');

        expect($php)->toContain('return new class extends Migration');
        expect($php)->toContain("Schema::table('users', function (Blueprint \$table) {");
        expect($php)->toContain("            \$table->text('bio')->nullable();");
        expect($php)->toContain("Schema::rename('users', 'members');");
        expect($php)->toContain("Schema::rename('members', 'users');");

        // Up alters the table before renaming it; down renames it back first
        expect(mb_strpos($php, "Schema::table('users'"))->toBeLessThan(mb_strpos($php, "Schema::rename('users', 'members')"));
        expect(mb_strpos($php, "Schema::rename('members', 'users')"))->toBeLessThan(mb_strpos($php, "\$table->dropColumn('bio')"));
    });

    test('produces insertable json and yaml fragments', function () {
        $old = new ModelSchema('User', 'users', []);
        $new = new ModelSchema('User', 'users', ['age' => new Field('age', 'integer', nullable: true)]);

        $result = $this->generator->generate($old, $new, ['class_name' => 'AddAgeToUsersTable']);

        $json = json_decode($result['json'], true);
        expect($json)->toHaveKey('alter_migration');
        expect($json['alter_migration']['class_name'])->toBe('AddAgeToUsersTable');
        expect($json['alter_migration']['table'])->toBe('users');
        expect($json['alter_migration']['operations'][0]['type'])->toBe('add_column');
        expect($json['alter_migration']['operations'][0])->not->toHaveKey('up');

        $yaml = Symfony\Component\Yaml\Yaml::parse($result['yaml']);
        expect($yaml['alter_migration']['up'])->toBe(["\$table->integer('age')->nullable();"]);
    });

    test('uses a default class name based on the table', function () {
        $old = new ModelSchema('User', 'user_profiles', []);
        $new = new ModelSchema('User', 'user_profiles', ['age' => new Field('age', 'integer', nullable: true)]);

        $data = $this->generator->buildMigrationData($old, $new);

        expect($data['class_name'])->toBe('AlterUserProfilesTable');
        expect($data['migration_name'])->toBe('alter_user_profiles_table');
    });

    test('is available through the GenerationService', function () {
        $old = new ModelSchema('User', 'users', []);
        $new = new ModelSchema('User', 'users', ['age' => new Field('age', 'integer', nullable: true)]);

        $result = (new GenerationService())->generateAlterMigration($old, $new);

        expect($result)->toHaveKeys(['metadata', 'json', 'yaml', 'php']);
        expect($result['php'])->toContain("\$table->integer('age')->nullable();");
    });
});
