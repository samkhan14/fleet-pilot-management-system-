<?php

namespace Tests\Feature;

use App\Models\Shipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use Tests\Support\OperationsWorld;
use Tests\TestCase;

class DispatchLockTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Commit the fixture so a second MySQL session can see and wait on the row.
     *
     * @var list<string>
     */
    protected array $connectionsToTransact = [];

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        Schema::disableForeignKeyConstraints();
        foreach (Schema::getTableListing(schemaQualified: false) as $table) {
            if (in_array($table, ['migrations', 'sqlite_sequence'], true)) {
                continue;
            }
            DB::table($table)->truncate();
        }
        Schema::enableForeignKeyConstraints();

        parent::tearDown();
    }

    public function test_a_second_connection_waits_on_the_shipment_row_lock(): void
    {
        $world = OperationsWorld::boot('harbor-lock');
        $shipment = OperationsWorld::ready($world);

        DB::beginTransaction();
        try {
            Shipment::query()->whereKey($shipment->id)->lockForUpdate()->first();

            $pdo = new PDO(
                'mysql:host=127.0.0.1;port=3306;dbname=fleetpilot_test',
                'fleetpilot',
                'fleetpilot',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
            );
            $pdo->exec('SET innodb_lock_wait_timeout = 2');
            $started = microtime(true);
            $pdo->beginTransaction();
            try {
                $statement = $pdo->prepare('SELECT id FROM shipments WHERE id = ? FOR UPDATE');
                $statement->execute([$shipment->id]);
                $this->fail('The second dispatcher should wait on the shipment lock.');
            } catch (\PDOException $exception) {
                $this->assertStringContainsString('Lock wait timeout', $exception->getMessage());
                $this->assertGreaterThan(1.5, microtime(true) - $started);
            }
        } finally {
            DB::rollBack();
        }
    }
}
