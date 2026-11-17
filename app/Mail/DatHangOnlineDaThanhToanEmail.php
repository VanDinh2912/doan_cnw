<?php

namespace App\Mail;

use App\Models\DonHang;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email thông báo đã nhận thanh toán cho đơn online (gửi từ webhook SePay).
 * Chỉ chứa thông tin đơn hàng cơ bản, không chứa dữ liệu nhạy cảm.
 */
class DatHangOnlineDaThanhToanEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public DonHang $donHang)
    {
        $this->donHang->loadMissing('chiTietDonHangs.sanPham');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Đã nhận thanh toán đơn '.$this->donHang->ma_hoadon,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.dat-hang-online-da-thanh-toan',
        );
    }
}
