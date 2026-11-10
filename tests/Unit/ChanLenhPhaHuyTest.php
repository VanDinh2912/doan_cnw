<?php

namespace Tests\Unit;

use App\Console\Commands\Concerns\ChanLenhPhaHuy;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class ChanLenhPhaHuyTest extends TestCase
{
    public function test_ngoai_production_luon_cho_phep(): void
    {
        $this->app['env'] = 'local';

        $ketQua = $this->chay([]);

        $this->assertSame(0, $ketQua['ma']);
        $this->assertStringContainsString('CHO_PHEP::memory:', $ketQua['output']);
    }

    public function test_production_thieu_co_force_dangerous_thi_chan(): void
    {
        $this->app['env'] = 'production';

        $ketQua = $this->chay([]);

        $this->assertSame(1, $ketQua['ma']);
        $this->assertStringContainsString('bị chặn ở production', $ketQua['output']);
        $this->assertStringNotContainsString('CHO_PHEP', $ketQua['output']);
    }

    public function test_production_nhap_sai_ten_database_thi_chan(): void
    {
        $this->app['env'] = 'production';

        $ketQua = $this->chay(['--force-dangerous' => true, '--ten-db-xac-nhan' => 'doan_cnw']);

        $this->assertSame(1, $ketQua['ma']);
        $this->assertStringContainsString('Tên database không khớp', $ketQua['output']);
        $this->assertStringNotContainsString('CHO_PHEP', $ketQua['output']);
    }

    public function test_production_du_co_va_dung_ten_thi_cho_phep(): void
    {
        $this->app['env'] = 'production';

        $ketQua = $this->chay(['--force-dangerous' => true, '--ten-db-xac-nhan' => ':memory:']);

        $this->assertSame(0, $ketQua['ma']);
        $this->assertStringContainsString('CHO_PHEP::memory:', $ketQua['output']);
    }

    public function test_production_khong_tuong_tac_va_thieu_ten_bi_chan(): void
    {
        $this->app['env'] = 'production';

        $ketQua = $this->chay(['--force-dangerous' => true]);

        $this->assertSame(1, $ketQua['ma']);
        $this->assertStringContainsString('--ten-db-xac-nhan', $ketQua['output']);
    }

    /**
     * @return array{ma: int, output: string}
     */
    private function chay(array $thamSo): array
    {
        $lenh = new LenhThuPhaHuy();
        $lenh->setLaravel($this->app);
        $input = new ArrayInput($thamSo);
        $input->setInteractive(false);
        $output = new BufferedOutput();

        $ma = $lenh->run($input, $output);

        return ['ma' => $ma, 'output' => $output->fetch()];
    }
}

class LenhThuPhaHuy extends Command
{
    use ChanLenhPhaHuy;

    protected $signature = 'thu:pha-huy {--force-dangerous} {--ten-db-xac-nhan=}';

    public function handle(): int
    {
        $tenDb = $this->xacNhanLenhPhaHuy();
        if ($tenDb === false) {
            return self::FAILURE;
        }

        $this->line('CHO_PHEP:'.$tenDb);

        return self::SUCCESS;
    }
}