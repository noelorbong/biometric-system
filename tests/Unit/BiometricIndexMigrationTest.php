<?php

namespace Tests\Unit;

use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;

class BiometricIndexMigrationTest extends TestCase
{
    public function test_index_migrations_support_nativephp_sqlite_and_preserve_records(): void
    {
        $previousApplication = Facade::getFacadeApplication();
        $database = new Manager;
        $database->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], 'nativephp');
        $database->getDatabaseManager()->setDefaultConnection('nativephp');
        $container = $database->getContainer();
        $container->instance('db', $database->getDatabaseManager());
        $container->bind('db.schema', fn () => $database->getConnection()->getSchemaBuilder());
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($container);

        try {
            $schema = $database->getConnection()->getSchemaBuilder();
            $schema->create('checkinout', function (Blueprint $table) {
                $table->id();
                $table->dateTime('CHECKTIME');
                $table->string('CHECKTYPE');
                $table->integer('SENSORID');
                $table->string('sn');
            });
            $database->getConnection()->table('checkinout')->insert([
                'CHECKTIME' => '2026-10-21 08:00:00', 'CHECKTYPE' => 'I', 'SENSORID' => 1, 'sn' => 'test',
            ]);
            $migrations = [];
            foreach (['2026_08_27_000001_add_biometric_log_query_indexes', '2026_08_27_000002_add_biometric_log_cursor_index'] as $name) {
                $migration = require __DIR__.'/../../database/migrations/'.$name.'.php';
                $migration->up();
                $migrations[] = $migration;
            }
            foreach (['checktime', 'checktype', 'sensorid', 'sn', 'checktime_id'] as $index) {
                $this->assertTrue($schema->hasIndex('checkinout', 'checkinout_'.$index.'_idx'));
            }
            foreach (array_reverse($migrations) as $migration) {
                $migration->down();
            }
            $this->assertFalse($schema->hasIndex('checkinout', 'checkinout_checktime_id_idx'));
            $this->assertSame(1, $database->getConnection()->table('checkinout')->count());
        } finally {
            $database->getDatabaseManager()->disconnect('nativephp');
            Facade::clearResolvedInstances();
            Facade::setFacadeApplication($previousApplication);
        }
    }
}
