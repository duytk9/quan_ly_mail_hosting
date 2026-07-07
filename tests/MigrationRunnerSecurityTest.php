<?php

declare(strict_types=1);

namespace MailPanel\Tests;

use PHPUnit\Framework\TestCase;

final class MigrationRunnerSecurityTest extends TestCase
{
    public function test_migration_runner_tracks_checksums_and_requires_baseline_for_existing_installs(): void
    {
        $source = file_get_contents(dirname(__DIR__) . '/scripts/migrate.php');
        $this->assertIsString($source);

        $this->assertStringContainsString('schema_migrations', $source);
        $this->assertStringContainsString('checksum', $source);
        $this->assertStringContainsString('--baseline-existing', $source);
        $this->assertStringContainsString('Existing database tables were detected without migration history.', $source);
        $this->assertStringContainsString('Applied migration file was modified', $source);
    }

    public function test_superseded_duplicate_foreign_key_migration_is_not_active(): void
    {
        $source = file_get_contents(dirname(__DIR__) . '/scripts/migrate.php');
        $this->assertIsString($source);

        $this->assertStringContainsString("'010_add_foreign_keys.sql'", $source);
        $this->assertStringContainsString('superseded by 010_add_foreign_keys_and_indexes.sql', $source);
    }
}
