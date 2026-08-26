<?php

namespace Tests\Feature\Database;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Finder\SplFileInfo;
use Tests\TestCase;

final class ModerationMigrationCompatibilityTest extends TestCase
{
    /** @var list<string> */
    private const ORIGIN_MAIN_MIGRATIONS = [
        '0001_01_01_000000_create_users_table.php',
        '0001_01_01_000001_create_cache_table.php',
        '0001_01_01_000002_create_jobs_table.php',
        '2026_05_25_194816_create_admins_table.php',
        '2026_06_10_044158_create_kycs_table.php',
        '2026_06_15_012922_create_stores_table.php',
        '2026_06_15_231613_create_permission_tables.php',
        '2026_06_17_233917_create_settings_table.php',
        '2026_06_18_184146_create_categories_table.php',
        '2026_06_29_042312_create_tags_table.php',
        '2026_06_29_204228_create_brands_table.php',
        '2026_07_03_035643_create_products_table.php',
        '2026_07_11_004629_create_category_product_table.php',
        '2026_07_11_005811_create_product_tag_table.php',
        '2026_07_13_031917_create_product_images_table.php',
        '2026_07_17_174345_create_attributes_table.php',
        '2026_07_17_174359_create_attribute_values_table.php',
        '2026_07_17_174852_create_product_attribute_values_table.php',
        '2026_07_27_035149_create_product_variants_table.php',
        '2026_07_27_035708_create_product_variant_attribute_value_table.php',
        '2026_08_11_010134_create_product_files_table.php',
    ];

    private ?string $adminConnection = null;

    private ?string $isolatedConnection = null;

    private ?string $isolatedDatabase = null;

    protected function tearDown(): void
    {
        try {
            if ($this->isolatedConnection !== null) {
                DB::purge($this->isolatedConnection);
            }

            if ($this->isolatedDatabase !== null && $this->adminConnection !== null) {
                DB::connection($this->adminConnection)->statement(
                    sprintf('DROP DATABASE IF EXISTS `%s`', $this->isolatedDatabase)
                );
            }
        } finally {
            parent::tearDown();
        }
    }

    public function test_fresh_install_builds_the_final_schema(): void
    {
        $this->createIsolatedDatabase();

        $exitCode = Artisan::call('migrate:fresh', [
            '--database' => $this->isolatedConnection,
            '--force' => true,
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());
        $this->assertFinalSchema();
    }

    public function test_origin_main_schema_upgrades_with_legacy_data_fail_closed(): void
    {
        $this->createIsolatedDatabase();

        $migrations = collect(File::files(database_path('migrations')))
            ->sortBy(static fn (SplFileInfo $file): string => $file->getFilename())
            ->values();
        $originNames = collect(self::ORIGIN_MAIN_MIGRATIONS);
        $originMain = $migrations
            ->filter(static fn (SplFileInfo $file): bool => $originNames->contains($file->getFilename()))
            ->values();
        $refactor = $migrations
            ->reject(static fn (SplFileInfo $file): bool => $originNames->contains($file->getFilename()))
            ->values();

        $this->assertSame(
            self::ORIGIN_MAIN_MIGRATIONS,
            $originMain->map(static fn (SplFileInfo $file): string => $file->getFilename())->all()
        );
        $this->assertNotEmpty($refactor);

        $this->runMigrations($originMain->all());

        $schema = Schema::connection($this->isolatedConnection);
        $this->assertFalse($schema->hasColumn('users', 'status'));
        $this->assertFalse($schema->hasColumn('users', 'deleted_at'));
        $this->assertFalse($schema->hasColumn('admins', 'status'));
        $this->assertFalse($schema->hasColumn('admins', 'deleted_at'));
        $this->assertFalse($schema->hasColumn('products', 'approved_status'));
        $this->assertFalse($schema->hasColumn('stores', 'is_active'));
        $this->assertStringContainsString('pending', $this->column('products', 'status')->column_type);

        $legacy = $this->insertRepresentativeLegacyData();
        $this->runMigrations($refactor->all());

        $this->assertFinalSchema();
        $connection = DB::connection($this->isolatedConnection);
        $user = $connection->table('users')->find($legacy['user']);
        $admin = $connection->table('admins')->find($legacy['admin']);
        $store = $connection->table('stores')->find($legacy['store']);
        $activeProduct = $connection->table('products')->find($legacy['active_product']);
        $pendingProduct = $connection->table('products')->find($legacy['pending_product']);

        $this->assertSame(0, (int) $user->status);
        $this->assertNull($user->deleted_at);
        $this->assertSame(0, (int) $admin->status);
        $this->assertNull($admin->deleted_at);
        $this->assertSame('pending', $store->status);
        $this->assertSame(0, (int) $store->is_active);
        $this->assertSame(0, (int) $store->moderation_version);
        $this->assertNull($store->reviewed_version);
        $this->assertNull($store->moderation_fingerprint);

        foreach ([$activeProduct, $pendingProduct] as $product) {
            $this->assertSame('active', $product->status);
            $this->assertSame('pending', $product->approved_status);
            $this->assertSame(0, (int) $product->moderation_version);
            $this->assertNull($product->reviewed_version);
            $this->assertNull($product->moderation_fingerprint);
        }

        $this->assertSame(0, $connection->table('stores')->where('is_active', true)->count());
        $this->assertSame(0, $connection->table('products')->where('approved_status', 'approved')->count());
        $this->assertSame(0, $connection->table('store_approval_reviews')->count());
        $this->assertSame(0, $connection->table('product_approval_reviews')->count());
    }

    private function createIsolatedDatabase(): void
    {
        $defaultConnection = (string) config('database.default');
        $baseConfig = config('database.connections.'.$defaultConnection);

        if (! is_array($baseConfig) || ($baseConfig['driver'] ?? null) !== 'mysql') {
            throw new RuntimeException('Migration compatibility tests require the Sail MySQL connection.');
        }

        $suffix = strtolower(Str::random(12));
        $this->adminConnection = 'moderation_admin_'.$suffix;
        $this->isolatedDatabase = 'moderation_migration_'.$suffix;
        $this->isolatedConnection = 'moderation_migration_'.$suffix;

        $adminConfig = $baseConfig;
        unset($adminConfig['url']);
        $adminConfig['database'] = 'mysql';
        $adminConfig['username'] = 'root';
        config(['database.connections.'.$this->adminConnection => $adminConfig]);
        DB::purge($this->adminConnection);

        DB::connection($this->adminConnection)->statement(sprintf(
            'CREATE DATABASE `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
            $this->isolatedDatabase
        ));

        unset($baseConfig['url']);
        $baseConfig['database'] = $this->isolatedDatabase;
        $baseConfig['username'] = 'root';
        config(['database.connections.'.$this->isolatedConnection => $baseConfig]);
        DB::purge($this->isolatedConnection);
    }

    private function runMigrations(array $migrations): void
    {
        $paths = [];

        foreach ($migrations as $migration) {
            $path = $migration->getRealPath();
            if ($path === false) {
                throw new RuntimeException('Cannot resolve migration path.');
            }
            $paths[] = $path;
        }

        $exitCode = Artisan::call('migrate', [
            '--database' => $this->isolatedConnection,
            '--path' => $paths,
            '--realpath' => true,
            '--force' => true,
        ]);
        $this->assertSame(0, $exitCode, Artisan::output());
    }

    private function assertFinalSchema(): void
    {
        $schema = Schema::connection($this->isolatedConnection);
        $this->assertTrue($schema->hasColumns('users', ['status', 'deleted_at']));
        $this->assertTrue($schema->hasColumns('admins', ['status', 'deleted_at']));
        $this->assertTrue($schema->hasColumn('products', 'approved_status'));
        $this->assertTrue($schema->hasColumn('stores', 'is_active'));

        foreach (['users', 'admins'] as $table) {
            $status = $this->column($table, 'status');
            $this->assertSame('tinyint(1)', $status->column_type);
            $this->assertSame('0', (string) $status->column_default);
            $this->assertSame('NO', $status->is_nullable);
        }

        $this->assertSame(
            'enum(\'active\',\'inactive\',\'draft\')',
            $this->column('products', 'status')->column_type
        );
        $approval = $this->column('products', 'approved_status');
        $this->assertSame('enum(\'approved\',\'pending\',\'rejected\')', $approval->column_type);
        $this->assertSame('pending', $approval->column_default);
        $this->assertSame('NO', $approval->is_nullable);

        $isActive = $this->column('stores', 'is_active');
        $this->assertSame('0', (string) $isActive->column_default);
        $this->assertSame('NO', $isActive->is_nullable);
    }

    private function column(string $table, string $column): object
    {
        $definition = $this->columnDefinition($table, $column);
        if ($definition === null) {
            throw new RuntimeException('Missing database column.');
        }

        return $definition;
    }

    private function columnDefinition(string $table, string $column): ?object
    {
        return DB::connection($this->isolatedConnection)->selectOne(
            'SELECT COLUMN_TYPE AS column_type, COLUMN_DEFAULT AS column_default, IS_NULLABLE AS is_nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$this->isolatedDatabase, $table, $column]
        );
    }

    private function insertRepresentativeLegacyData(): array
    {
        $connection = DB::connection($this->isolatedConnection);
        $token = strtolower(Str::random(8));
        $user = (int) $connection->table('users')->insertGetId([
            'name' => 'Legacy Vendor',
            'email' => 'legacy-vendor-'.$token.'@example.test',
            'password' => 'legacy-password-hash',
            'user_type' => 'vendor',
        ]);
        $admin = (int) $connection->table('admins')->insertGetId([
            'name' => 'Legacy Admin',
            'email' => 'legacy-admin-'.$token.'@example.test',
            'password' => 'legacy-password-hash',
        ]);
        $store = (int) $connection->table('stores')->insertGetId([
            'seller_id' => $user,
            'name' => 'Legacy Store',
            'slug' => 'legacy-store-'.$token,
            'status' => 'active',
        ]);
        $activeProduct = (int) $connection->table('products')->insertGetId([
            'store_id' => $store,
            'name' => 'Legacy Active Product',
            'slug' => 'legacy-active-product-'.$token,
            'description' => 'Legacy product content.',
            'product_type' => 'physical',
            'status' => 'active',
        ]);
        $pendingProduct = (int) $connection->table('products')->insertGetId([
            'store_id' => $store,
            'name' => 'Legacy Pending Product',
            'slug' => 'legacy-pending-product-'.$token,
            'description' => 'Legacy pending content.',
            'product_type' => 'physical',
            'status' => 'pending',
        ]);

        return [
            'user' => $user,
            'admin' => $admin,
            'store' => $store,
            'active_product' => $activeProduct,
            'pending_product' => $pendingProduct,
        ];
    }
}
