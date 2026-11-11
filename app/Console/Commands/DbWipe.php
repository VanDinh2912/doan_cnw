<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ChanLenhPhaHuy;
use Illuminate\Database\Console\WipeCommand as BaseWipeCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'db:wipe')]
class DbWipe extends BaseWipeCommand
{
    use ChanLenhPhaHuy;

    protected $description = 'Xóa toàn bộ bảng, view và type (có chốt chặn production)';

    public function handle()
    {
        if ($this->xacNhanLenhPhaHuy() === false) {
            return self::FAILURE;
        }

        return parent::handle();
    }

    protected function getOptions()
    {
        return array_merge(parent::getOptions(), [
            ['force-dangerous', null, InputOption::VALUE_NONE, 'Cho phép chạy lệnh phá hủy ở production (vẫn phải nhập lại tên database)'],
            ['ten-db-xac-nhan', null, InputOption::VALUE_OPTIONAL, 'Tên database xác nhận khi chạy production không tương tác'],
        ]);
    }
}
