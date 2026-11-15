<?php

namespace App\Models\Concerns;

use App\Models\NhatKyHoatDong;
use App\Services\NhatKyService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

trait GhiNhatKy
{
    protected static function bootGhiNhatKy(): void
    {
        static::created(function ($model): void {
            $model->ghiThayDoiNhatKy('tao', [], $model->getAttributes());
        });
        static::updated(function ($model): void {
            $model->ghiThayDoiNhatKy('sua', $model->getOriginal(), $model->getChanges());
        });
        static::deleted(function ($model): void {
            $model->ghiThayDoiNhatKy('xoa', $model->getOriginal(), []);
        });
    }

    public function ghiThayDoiNhatKy(string $hanhDong, array $truoc, array $sau): void
    {
        if (! Schema::hasTable('activity_logs') || ! Schema::hasColumn('activity_logs', 'truoc')) {
            return;
        }
        $request = app()->bound('request') ? request() : null;
        $data = [
            'user_id' => Auth::id(),
            'hanh_dong' => $hanhDong,
            'doi_tuong' => $this->getTable(),
            'doi_tuong_loai' => class_basename($this),
            'doi_tuong_id' => $this->getKey(),
            'mo_ta' => ucfirst($hanhDong).' '.$this->getTable().' #'.$this->getKey(),
            'truoc' => NhatKyService::anToan($truoc),
            'sau' => NhatKyService::anToan($sau),
            'ip' => $request?->ip(),
            'user_agent' => substr((string) $request?->userAgent(), 0, 500) ?: null,
            'nguon' => app()->runningInConsole() ? 'artisan' : 'web',
        ];
        NhatKyHoatDong::create($data);
    }
}
