<?php

namespace App\Mail;

use App\Models\DonHang;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email xác nhận đặt hàng online. Chỉ chứa thông tin đơn hàng cơ bản:
 * mã đơn, sản phẩm, tổng tiền, phương thức thanh toán, hạn giữ hàng và
 * hướng dẫn tra cứu. KHÔNG chứa checkout token hay dữ liệu nhạy cảm.
 */
class DatHangOnlineXacNhanEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public DonHang $donHang)
    {
        $this->donHang->loadMissing('chiTietDonHangs.sanPham');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Xác nhận đơn hàng '.$this->donHang->ma_hoadon,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.dat-hang-online-xac-nhan',
        );
    }
}
