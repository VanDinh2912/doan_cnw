<?php

namespace App\Listeners;

use App\Support\ChanThaoTacCsdlProd;
use Illuminate\Console\Events\CommandStarting;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Chặn migrate:fresh/refresh/reset/rollback/wipe/seed và migrate thường
 * khi database đích là prod, trừ khi có cờ CHO_PHEP_MIGRATE_PROD=1.
 * migrate:status, migrate --pretend và mọi lệnh khác không bị ảnh hưởng.
 */
class ChanThaoTacCsdlProdListener
{
    private const LENH_NGUY_HIEM = [
        'migrate',
        'migrate:fresh',
        'migrate:refresh',
        'migrate:reset',
        'migrate:rollback',
        'db:wipe',
        'db:seed',
    ];

    public function handle(CommandStarting $event): void
    {
        $lenh = $event->command;
        if (! in_array($lenh, self::LENH_NGUY_HIEM, true)) {
            return;
        }

        // migrate --pretend chỉ in SQL, không ghi dữ liệu → cho phép.
        if ($lenh === 'migrate' && $this->coTuyChon($event->input, '--pretend')) {
            return;
        }

        $database = $this->giaTriTuyChon($event->input, '--database');
        $databaseOption = is_string($database) && $database !== '' ? $database : null;

        if (! ChanThaoTacCsdlProd::nenChan($databaseOption)) {
            return;
        }

        throw new RuntimeException(ChanThaoTacCsdlProd::thongBao());
    }

    private function coTuyChon(InputInterface $input, string $ten): bool
    {
        try {
            return (bool) $input->hasParameterOption($ten, true);
        } catch (\Throwable) {
            return false;
        }
    }

    private function giaTriTuyChon(InputInterface $input, string $ten): mixed
    {
        try {
            $giaTri = $input->getParameterOption($ten, null, true);

            return is_string($giaTri) || $giaTri === null ? $giaTri : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
