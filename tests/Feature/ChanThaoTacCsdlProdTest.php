<?php

namespace Tests\Feature;

use App\Listeners\ChanThaoTacCsdlProdListener;
use App\Support\ChanThaoTacCsdlProd;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use Tests\TestCase;

/**
 * Guard chặn migrate/db:* khi database đích là doan_cnw.
 *
 * Lưu ý: Laravel KHÔNG dispatch CommandStarting khi runningUnitTests()
 * (xem Illuminate\Foundation\Console\Kernel::rerouteSymfonyCommandEvents),
 * nên test gọi trực tiếp listener->handle() với event CommandStarting
 * thay vì $this->artisan(). Guard vẫn hoạt động trên CLI thật.
 */
class ChanThaoTacCsdlProdTest extends TestCase
{
    use RefreshDatabase;

    private function listener(): ChanThaoTacCsdlProdListener
    {
        return new ChanThaoTacCsdlProdListener;
    }

    private function suKien(string $lenh, string $input = ''): CommandStarting
    {
        return new CommandStarting($lenh, new StringInput($input), new NullOutput);
    }

    /** @param array<string, mixed> $thamSo */
    private function suKienMang(string $lenh, array $thamSo): CommandStarting
    {
        $dinhNghia = new \Symfony\Component\Console\Input\InputDefinition([
            new \Symfony\Component\Console\Input\InputOption('database', null, \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL),
            new \Symfony\Component\Console\Input\InputOption('pretend', null, \Symfony\Component\Console\Input\InputOption::VALUE_NONE),
        ]);
        $input = new ArrayInput($thamSo, $dinhNghia);
        $input->bind($dinhNghia);

        return new CommandStarting($lenh, $input, new NullOutput);
    }

    /**
     * Giả lập "database đích là prod" qua config — guard chỉ đọc config,
     * PDO sqlite :memory: của RefreshDatabase không bị ảnh hưởng.
     */
    private function troDatabase(string $ten): void
    {
        Config::set('database.connections.sqlite.database', $ten);
    }

    private function datCoPhep(bool $bat): void
    {
        if ($bat) {
            putenv(ChanThaoTacCsdlProd::CO_PHEP.'=1');
        } else {
            putenv(ChanThaoTacCsdlProd::CO_PHEP);
        }
    }

    private function voiProd(callable $fn): void
    {
        $this->troDatabase('doan_cnw');
        $this->datCoPhep(false);
        try {
            $fn();
        } finally {
            $this->datCoPhep(false);
            $this->troDatabase(':memory:');
        }
    }

    // ---------- Predicate ----------

    public function test_nhan_dien_prod_va_dev(): void
    {
        $this->troDatabase('doan_cnw');
        $this->assertTrue(ChanThaoTacCsdlProd::laProd());
        $this->assertTrue(ChanThaoTacCsdlProd::nenChan());

        foreach (['doan_cnw_dev', 'doan_cnw_dev_cu', ':memory:'] as $dev) {
            $this->troDatabase($dev);
            $this->assertFalse(ChanThaoTacCsdlProd::laProd(), $dev);
            $this->assertFalse(ChanThaoTacCsdlProd::nenChan(), $dev);
        }
    }

    public function test_co_phep_chi_nhin_bien_moi_truong_tien_trinh(): void
    {
        $this->troDatabase('doan_cnw');
        $this->datCoPhep(false);
        $this->assertFalse(ChanThaoTacCsdlProd::duocPhepRoRang());
        $this->assertTrue(ChanThaoTacCsdlProd::nenChan());

        $this->datCoPhep(true);
        $this->assertTrue(ChanThaoTacCsdlProd::duocPhepRoRang());
        $this->assertFalse(ChanThaoTacCsdlProd::nenChan());

        $this->datCoPhep(false);
    }

    public function test_database_option_ghi_de_default(): void
    {
        Config::set('database.connections.prod_gia', ['driver' => 'sqlite', 'database' => 'doan_cnw']);
        Config::set('database.connections.dev_gia', ['driver' => 'sqlite', 'database' => 'doan_cnw_dev']);

        $this->assertTrue(ChanThaoTacCsdlProd::laProd('prod_gia'));
        $this->assertTrue(ChanThaoTacCsdlProd::nenChan('prod_gia'));
        $this->assertFalse(ChanThaoTacCsdlProd::laProd('dev_gia'));
        $this->assertFalse(ChanThaoTacCsdlProd::nenChan('dev_gia'));
    }

    // ---------- Listener ----------

    public function test_listener_da_dang_ky(): void
    {
        $raw = $this->app['events']->getRawListeners()[\Illuminate\Console\Events\CommandStarting::class] ?? [];
        $this->assertContains(ChanThaoTacCsdlProdListener::class, $raw);
    }

    public function test_moi_lenh_nguy_hiem_bi_chan_khi_tro_prod(): void
    {
        $this->voiProd(function () {
            foreach (['migrate', 'migrate:fresh', 'migrate:refresh', 'migrate:reset', 'migrate:rollback', 'db:wipe', 'db:seed'] as $lenh) {
                try {
                    $this->listener()->handle($this->suKien($lenh));
                    $this->fail("{$lenh} phải bị chặn khi trỏ prod");
                } catch (RuntimeException $e) {
                    $this->assertStringContainsString('bị chặn', $e->getMessage());
                    $this->assertStringContainsString(ChanThaoTacCsdlProd::CO_PHEP, $e->getMessage());
                }
            }
        });
    }

    public function test_thong_bao_huong_dan_dung(): void
    {
        $this->voiProd(function () {
            try {
                $this->listener()->handle($this->suKien('migrate'));
                $this->fail('phải ném RuntimeException');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('doan_cnw', $e->getMessage());
                $this->assertStringContainsString('CHO_PHEP_MIGRATE_PROD=1', $e->getMessage());
            }
        });
    }

    public function test_migrate_status_va_pretend_khong_bi_chan(): void
    {
        $this->voiProd(function () {
            // migrate:status không nằm trong danh sách → return sớm.
            $this->listener()->handle($this->suKien('migrate:status'));
            // migrate --pretend chỉ in SQL → cho qua.
            $this->listener()->handle($this->suKienMang('migrate', ['--pretend' => true]));
            $this->addToAssertionCount(2);
        });
    }

    public function test_lenh_khac_khong_bi_anh_huong(): void
    {
        $this->voiProd(function () {
            foreach (['about', 'list', 'diem:doi-soat', 'migrate:status', 'inspire'] as $lenh) {
                $this->listener()->handle($this->suKien($lenh));
            }
            $this->addToAssertionCount(1);
        });
    }

    public function test_co_phep_env_mo_khoa_chan(): void
    {
        $this->troDatabase('doan_cnw');
        $this->datCoPhep(true);
        try {
            $this->listener()->handle($this->suKien('migrate'));
            $this->listener()->handle($this->suKien('db:wipe'));
            $this->addToAssertionCount(1);
        } finally {
            $this->datCoPhep(false);
            $this->troDatabase(':memory:');
        }
    }

    public function test_khong_chan_khi_tro_dev(): void
    {
        foreach (['doan_cnw_dev', 'doan_cnw_dev_cu', ':memory:'] as $dev) {
            $this->troDatabase($dev);
            $this->datCoPhep(false);
            $this->listener()->handle($this->suKien('migrate'));
            $this->listener()->handle($this->suKien('db:wipe'));
        }
        $this->troDatabase(':memory:');
        $this->addToAssertionCount(1);
    }

    public function test_database_option_ghi_de_default_trong_listener(): void
    {
        // Default là dev nhưng --database=prod_gia (doan_cnw) → vẫn chặn.
        Config::set('database.connections.prod_gia', ['driver' => 'sqlite', 'database' => 'doan_cnw']);
        $this->troDatabase('doan_cnw_dev');
        $this->datCoPhep(false);

        try {
            $this->listener()->handle($this->suKienMang('migrate', ['--database' => 'prod_gia']));
            $this->fail('phải chặn theo --database');
        } catch (RuntimeException) {
            $this->addToAssertionCount(1);
        } finally {
            $this->troDatabase(':memory:');
        }
    }
}
