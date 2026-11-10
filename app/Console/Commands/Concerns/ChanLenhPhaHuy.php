<?php

namespace App\Console\Commands\Concerns;

/**
 * Chốt chặn cho các lệnh xóa dữ liệu. Ở production phải có --force-dangerous
 * VÀ nhập lại đúng tên database đang kết nối; thiếu một yếu tố là dừng hẳn.
 */
trait ChanLenhPhaHuy
{
    /**
     * @return string|false Tên database đã xác nhận, hoặc false nếu phải dừng.
     */
    protected function xacNhanLenhPhaHuy(): string|false
    {
        $tenDb = $this->tenDatabaseHienTai();

        if (! app()->environment('production')) {
            return $tenDb;
        }

        if (! $this->option('force-dangerous')) {
            $this->error('Lệnh này bị chặn ở production. Thêm --force-dangerous rồi nhập lại tên database nếu thật sự cần.');

            return false;
        }

        $nhapLai = $this->option('ten-db-xac-nhan');
        if ($nhapLai === null || $nhapLai === '') {
            if (! $this->input->isInteractive()) {
                $this->error('Chạy không tương tác phải kèm --ten-db-xac-nhan=<tên database>.');

                return false;
            }
            $nhapLai = $this->ask("Nhập lại chính xác tên database \"{$tenDb}\" để xác nhận");
        }

        if ($tenDb === '' || ! is_string($nhapLai) || trim($nhapLai) !== $tenDb) {
            $this->error('Tên database không khớp. Đã hủy, không có thay đổi nào.');

            return false;
        }

        return $tenDb;
    }

    protected function tenDatabaseHienTai(): string
    {
        if (! $this->hasOption('database')) {
            return (string) config('database.connections.'.config('database.default').'.database');
        }

        return (string) $this->laravel['db']->connection($this->option('database'))->getDatabaseName();
    }

    /**
     * Chuyển xác nhận đã có sang lệnh con để không hỏi lại.
     *
     * @return array<string, mixed>
     */
    protected function tuyChonXacNhanPhaHuy(string $tenDb): array
    {
        return ['--force-dangerous' => true, '--ten-db-xac-nhan' => $tenDb];
    }
}