<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ChanLenhPhaHuy;
use Illuminate\Database\Console\Migrations\FreshCommand as BaseFreshCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Ghi đè migrate:fresh để thêm chốt chặn production. Không đổi cách Laravel
 * xóa bảng và chạy lại migration, chỉ chặn trước khi nó kịp làm gì.
 */
#[AsCommand(name: 'migrate:fresh')]
class MigrateFresh extends BaseFreshCommand
{
    use ChanLenhPhaHuy;

    protected $description = 'Xóa toàn bộ bảng và chạy lại migration (có chốt chặn production)';

    protected ?string $tenDbDaXacNhan = null;

    public function handle()
    {
        $tenDb = $this->xacNhanLenhPhaHuy();
        if ($tenDb === false) {
            return self::FAILURE;
        }

        $this->tenDbDaXacNhan = $tenDb;

        try {
            return parent::handle();
        } finally {
            $this->tenDbDaXacNhan = null;
        }
    }

    public function callSilent($command, array $arguments = [])
    {
        if ($command === 'db:wipe' && $this->tenDbDaXacNhan !== null) {
            // Laravel chỉ truyền --force; lệnh con vẫn phải tự kiểm tra xác nhận database.
            $arguments = array_merge($arguments, $this->tuyChonXacNhanPhaHuy($this->tenDbDaXacNhan));
        }

        return parent::callSilent($command, $arguments);
    }

    protected function getOptions()
    {
        return array_merge(parent::getOptions(), [
            ['force-dangerous', null, InputOption::VALUE_NONE, 'Cho phép chạy lệnh phá hủy ở production (vẫn phải nhập lại tên database)'],
            ['ten-db-xac-nhan', null, InputOption::VALUE_OPTIONAL, 'Tên database xác nhận khi chạy production không tương tác'],
        ]);
    }
}