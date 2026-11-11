<?php

namespace App\Console\Commands;

use App\Exceptions\SepayApiException;
use App\Services\SepayApiClient;
use Illuminate\Console\Command;

class SepayKiemTraKetNoi extends Command
{
    protected $signature = 'sepay:kiem-tra-ket-noi';

    protected $description = 'Kiểm tra token và kết nối SePay API (chỉ đọc, không in bí mật)';

    public function __construct(private readonly SepayApiClient $client)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $this->client->kiemTraKetNoi();
        } catch (SepayApiException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Kết nối SePay API OK.');

        return self::SUCCESS;
    }
}
