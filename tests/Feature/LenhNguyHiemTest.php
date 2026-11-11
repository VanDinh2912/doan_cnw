<?php

namespace Tests\Feature;

use App\Console\Commands\DbWipe;
use App\Console\Commands\MigrateFresh;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Output\NullOutput;
use Tests\TestCase;

class LenhNguyHiemTest extends TestCase
{
    protected Builder $schema;
    protected Migrator $migrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['env'] = 'production';

        config([
            'database.default' => 'ket_noi_mac_dinh',
            'database.connections.ket_noi_mac_dinh.database' => 'db_mac_dinh',
            'database.connections.ket_noi_khac.database' => 'db_trong_config',
        ]);

        $this->schema = Mockery::mock(Builder::class);
        $macDinh = $this->ketNoiGiaLap('db_mac_dinh');
        $khac = $this->ketNoiGiaLap('db_duoc_chon');
        $db = Mockery::mock(DatabaseManager::class);
        $db->shouldReceive('connection')->with(null)->andReturn($macDinh);
        $db->shouldReceive('connection')->with('ket_noi_mac_dinh')->andReturn($macDinh);
        $db->shouldReceive('connection')->with('ket_noi_khac')->andReturn($khac);
        DB::swap($db);

        $this->migrator = Mockery::mock(Migrator::class);
        $this->migrator->shouldNotReceive('run');
        $this->app->instance('migrator', $this->migrator);
        $this->app->instance(Migrator::class, $this->migrator);
    }

    protected function tearDown(): void
    {
        $this->app['env'] = 'testing';
        parent::tearDown();
    }

    public static function lenhPhaHuy(): array
    {
        return [
            'migrate:fresh' => ['migrate:fresh'],
            'db:wipe' => ['db:wipe'],
        ];
    }

    public function test_dang_ky_dung_command_override_va_cac_tuy_chon(): void
    {
        $commands = Artisan::all();

        foreach (['migrate:fresh' => MigrateFresh::class, 'db:wipe' => DbWipe::class] as $tenLenh => $class) {
            $this->assertSame($class, get_class($commands[$tenLenh]));

            foreach (['database', 'force', 'drop-views', 'drop-types', 'force-dangerous', 'ten-db-xac-nhan'] as $option) {
                $this->assertTrue($commands[$tenLenh]->getDefinition()->hasOption($option));
            }
        }
    }

    #[DataProvider('lenhPhaHuy')]
    public function test_lenh_bi_chan_khi_thieu_co_force_dangerous_ke_ca_force(string $tenLenh): void
    {
        foreach ([false, true] as $force) {
            $this->artisan($tenLenh, ['--force' => $force, '--no-interaction' => true])
                ->expectsOutputToContain('bị chặn ở production')
                ->assertExitCode(1)
                ->run();
        }
    }

    #[DataProvider('lenhPhaHuy')]
    public function test_lenh_bi_chan_khi_nhap_sai_ten_database(string $tenLenh): void
    {
        $this->artisan($tenLenh, [
            '--force' => true,
            '--force-dangerous' => true,
            '--ten-db-xac-nhan' => 'db_sai',
            '--no-interaction' => true,
        ])->expectsOutputToContain('Tên database không khớp')->assertExitCode(1);
    }

    #[DataProvider('lenhPhaHuy')]
    public function test_lenh_khong_tuong_tac_bi_chan_khi_thieu_ten_database(string $tenLenh): void
    {
        $this->artisan($tenLenh, [
            '--force' => true,
            '--force-dangerous' => true,
            '--no-interaction' => true,
        ])->expectsOutputToContain('phải kèm --ten-db-xac-nhan')->assertExitCode(1);
    }

    #[DataProvider('lenhPhaHuy')]
    public function test_connection_duoc_chon_khong_chap_nhan_db_mac_dinh_ten_connection_hay_config_tho(string $tenLenh): void
    {
        foreach (['db_mac_dinh', 'ket_noi_khac', 'db_trong_config'] as $tenDb) {
            $this->artisan($tenLenh, [
                '--database' => 'ket_noi_khac',
                '--force' => true,
                '--force-dangerous' => true,
                '--ten-db-xac-nhan' => $tenDb,
                '--no-interaction' => true,
            ])->expectsOutputToContain('Tên database không khớp')->assertExitCode(1)->run();
        }
    }

    #[DataProvider('lenhPhaHuy')]
    public function test_cho_phep_khi_xac_nhan_dung_database_thuc_cua_connection_duoc_chon(string $tenLenh): void
    {
        $this->giaLapPhaHuy($tenLenh, 'ket_noi_khac', 'db_duoc_chon');

        $this->artisan($tenLenh, [
            '--database' => 'ket_noi_khac',
            '--force' => true,
            '--force-dangerous' => true,
            '--ten-db-xac-nhan' => 'db_duoc_chon',
            '--drop-views' => true,
            '--drop-types' => true,
            '--no-interaction' => true,
        ])->assertExitCode(0);
    }

    #[DataProvider('lenhPhaHuy')]
    public function test_cho_phep_nhap_lai_database_tuong_tac(string $tenLenh): void
    {
        $this->giaLapPhaHuy($tenLenh);

        $this->artisan($tenLenh, [
            '--force' => true,
            '--force-dangerous' => true,
            '--drop-views' => true,
            '--drop-types' => true,
        ])->expectsQuestion('Nhập lại chính xác tên database "db_mac_dinh" để xác nhận', 'db_mac_dinh')
            ->assertExitCode(0);
    }

    #[DataProvider('lenhPhaHuy')]
    public function test_ngoai_production_khong_can_xac_nhan_pha_huy(string $tenLenh): void
    {
        $this->app['env'] = 'testing';
        $this->giaLapPhaHuy($tenLenh);

        $this->artisan($tenLenh, [
            '--drop-views' => true,
            '--drop-types' => true,
            '--no-interaction' => true,
        ])->assertExitCode(0);
    }

    public function test_xac_nhan_forward_khong_vo_hieu_guard_lenh_con_hoac_duoc_tai_su_dung(): void
    {
        $lenh = $this->giaLapPhaHuy('migrate:fresh');

        $this->artisan('migrate:fresh', [
            '--force' => true,
            '--force-dangerous' => true,
            '--ten-db-xac-nhan' => 'db_mac_dinh',
            '--drop-views' => true,
            '--drop-types' => true,
            '--no-interaction' => true,
        ])->assertExitCode(0)->run();

        $this->assertSame(1, $lenh->callSilent('db:wipe', [
            '--database' => 'ket_noi_khac',
            '--force' => true,
            '--force-dangerous' => true,
            '--ten-db-xac-nhan' => 'db_mac_dinh',
            '--no-interaction' => true,
        ]));
        $this->assertSame(1, $lenh->callSilent('db:wipe', ['--force' => true, '--no-interaction' => true]));
    }

    public function test_call_silent_khong_them_xac_nhan_cho_lenh_khac(): void
    {
        $lenh = Mockery::mock(MigrateFreshGiaLap::class.'[call,runCommand]', [$this->migrator])->shouldAllowMockingProtectedMethods();
        $lenh->shouldReceive('runCommand')->once()
            ->with('db:seed', ['--force' => true], Mockery::type(NullOutput::class))
            ->andReturn(0);

        $this->assertSame(0, $lenh->callSilent('db:seed', ['--force' => true]));
    }

    public function test_demo_reset_bi_chan_khi_thieu_co_force_dangerous(): void
    {
        $this->artisan('demo:reset', ['--force' => true])
            ->expectsOutputToContain('bị chặn ở production')
            ->assertExitCode(1);
    }

    public function test_documents_import_bi_chan_khi_thieu_co_force_dangerous(): void
    {
        $this->artisan('documents:import', ['--customers' => true])
            ->expectsOutputToContain('bị chặn ở production')
            ->assertExitCode(1);
    }

    private function ketNoiGiaLap(string $tenDb): Connection
    {
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getDatabaseName')->andReturn($tenDb);
        $connection->shouldReceive('getSchemaBuilder')->andReturn($this->schema);
        $connection->shouldReceive('disconnect')->andReturnNull();

        return $connection;
    }

    private function giaLapPhaHuy(string $tenLenh, ?string $connection = null, string $tenDb = 'db_mac_dinh'): ?MigrateFresh
    {
        $this->schema->shouldReceive('dropAllTables')->once()->andReturnNull();
        $this->schema->shouldReceive('dropAllViews')->once()->andReturnNull();
        $this->schema->shouldReceive('dropAllTypes')->once()->andReturnNull();

        if ($tenLenh !== 'migrate:fresh') {
            return null;
        }

        $this->migrator->shouldReceive('usingConnection')->once()
            ->with($connection, Mockery::type('callable'))
            ->andReturnUsing(fn ($name, $callback) => $callback());
        $this->migrator->shouldReceive('repositoryExists')->once()->andReturnTrue();

        $lenh = Mockery::mock(MigrateFreshGiaLap::class.'[call,runCommand]', [$this->migrator])->shouldAllowMockingProtectedMethods();
        $lenh->shouldReceive('call')->once()
            ->with('migrate', array_filter(['--database' => $connection, '--force' => true]))
            ->andReturn(0);
        $lenh->shouldReceive('runCommand')->passthru()->byDefault();
        $lenh->shouldReceive('runCommand')->once()->with('db:wipe', array_filter([
            '--database' => $connection,
            '--drop-views' => true,
            '--drop-types' => true,
            '--force' => true,
            '--force-dangerous' => true,
            '--ten-db-xac-nhan' => $tenDb,
        ]), Mockery::type(NullOutput::class))->passthru();

        Artisan::all()['migrate:fresh']->getApplication()->add($lenh);

        return $lenh;
    }
}

// Mockery sao chép AsCommand nhưng làm mất tham số; lớp con không có attribute này.
class MigrateFreshGiaLap extends MigrateFresh
{
}
