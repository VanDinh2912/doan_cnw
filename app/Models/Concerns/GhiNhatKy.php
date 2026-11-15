<?php

namespace App\Models\Concerns;

use App\Models\NhatKyHoatDong;
use App\Services\NhatKyService;
use App\Support\KiemTraSchema;
use Illuminate\Support\Facades\Auth;

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
        // Guard "code đi trước migration": DB chưa có bảng/cột nhật ký thì bỏ
        // qua, KHÔNG làm hỏng giao dịch nghiệp vụ. Schema check cache tĩnh qua
        // KiemTraSchema (tối đa 1 truy vấn/cột/tiến trình). Nếu insert vẫn lỗi
        // (DB thật sự hỏng), exception cố ý để nổi — nhật ký kiểm toán không
        // được nuốt lỗi. TODO(gỡ guard): xóa khối này sau khi migration nhật
        // ký đã áp lên DB thật — xem docs báo cáo Stage 3-4.
        if (! KiemTraSchema::coBang('activity_logs')) {
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
        foreach ($data as $cot => $giaTri) {
            if (! KiemTraSchema::coCot('activity_logs', $cot)) {
                unset($data[$cot]);
            }
        }
        NhatKyHoatDong::create($data);
    }
}
