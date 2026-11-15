<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\NhatKyService;
use App\Support\KiemTraSchema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class TaiKhoanNhanVienController extends Controller
{
    /**
     * Danh sách tài khoản nhân viên (admin, thungan, thukho)
     * Chỉ admin được truy cập (qua middleware role:admin)
     */
    public function index(Request $request)
    {
        if ($this->thieuMigrationStage3()) {
            return $this->baoThieuMigration();
        }

        $query = User::whereIn('role', ['admin', 'thungan', 'thukho'])
            ->with('khachHang'); // Eager load để tránh N+1

        // Tìm kiếm theo tên hoặc email
        if ($request->filled('q')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->q . '%')
                    ->orWhere('email', 'like', '%' . $request->q . '%');
            });
        }

        $users = $query->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        return view('taikhoan.danhsach', compact('users'));
    }

    /**
     * Form tạo tài khoản nhân viên mới
     * Chỉ admin được truy cập (qua middleware role:admin)
     */
    public function create()
    {
        return view('taikhoan.them');
    }

    /**
     * Lưu tài khoản nhân viên mới
     * Chỉ admin được truy cập (qua middleware role:admin)
     */
    public function store(Request $request)
    {
        if ($this->thieuMigrationStage3()) {
            return $this->baoThieuMigration();
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'role' => 'required|in:admin,thungan,thukho',
        ]);

        // Mật khẩu tạm sinh ngẫu nhiên đủ mạnh
        $tempPassword = Str::password(16, true, true, true);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'password' => Hash::make($tempPassword),
            'bat_buoc_doi_mat_khau' => true, // Bắt buộc đổi ở lần đăng nhập đầu
        ]);

        // Ghi nhật ký (không ghi mật khẩu)
        NhatKyService::ghi(
            request(),
            'tao',
            'users',
            $user->id,
            "Tạo tài khoản nhân viên: {$user->name} ({$user->role})",
            ['role' => $validated['role']],
            ['role' => $validated['role'], 'bat_buoc_doi_mat_khau' => true],
            'web'
        );

        // Mật khẩu tạm chỉ hiển thị trong flash session (request kế tiếp)
        return redirect()
            ->route('taikhoan.danhsach')
            ->with('temp_password', $tempPassword)
            ->with('success', 'Đã tạo tài khoản thành công. Mật khẩu tạm hiển thị bên dưới (chỉ hiện một lần).');
    }

    /**
     * Form sửa tài khoản nhân viên
     * Chỉ admin được truy cập (qua middleware role:admin)
     */
    public function edit(User $user)
    {
        // Chỉ cho phép sửa nhân viên, không sửa khách hàng
        if (!in_array($user->role, ['admin', 'thungan', 'thukho'])) {
            abort(403, 'Chỉ được sửa tài khoản nhân viên.');
        }

        return view('taikhoan.sua', compact('user'));
    }

    /**
     * Cập nhật tài khoản nhân viên
     * Chỉ admin được truy cập (qua middleware role:admin)
     */
    public function update(Request $request, User $user)
    {
        if ($this->thieuMigrationStage3()) {
            return $this->baoThieuMigration();
        }

        // Chỉ cho phép sửa nhân viên
        if (!in_array($user->role, ['admin', 'thungan', 'thukho'])) {
            abort(403, 'Chỉ được sửa tài khoản nhân viên.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'role' => 'required|in:admin,thungan,thukho',
        ]);

        // QUY TẮC BẮT BUỘC:
        // 1. Admin không tự khóa mình
        if ($user->id === Auth::id() && $user->khoa_luc !== null) {
            return back()->with('error', 'Không thể tự sửa tài khoản đã bị khóa.');
        }

        // 2. Admin không tự hạ vai trò mình
        if ($user->id === Auth::id() && $user->role === 'admin' && $validated['role'] !== 'admin') {
            return back()->with('error', 'Không thể tự hạ vai trò admin.');
        }

        // 3. Luôn còn ít nhất một admin hoạt động
        if ($user->role === 'admin' && $validated['role'] !== 'admin') {
            $adminCount = User::where('role', 'admin')
                ->where('id', '!=', $user->id)
                ->where('khoa_luc', null)
                ->count();

            if ($adminCount === 0) {
                return back()->with('error', 'Phải còn ít nhất một admin hoạt động.');
            }
        }

        // 4. Email không được trùng với user khác
        if ($validated['email'] !== $user->email) {
            $request->validate([
                'email' => 'required|email|unique:users,email,' . $user->id,
            ]);
        }

        $oldValues = $user->only(['name', 'role']);
        $user->update($validated);
        $newValues = $user->only(['name', 'role']);

        // Ghi nhật ký nếu có thay đổi
        if ($oldValues !== $newValues) {
            NhatKyService::ghi(
                request(),
                'sua',
                'users',
                $user->id,
                "Sửa tài khoản nhân viên: {$user->name}",
                $oldValues,
                $newValues,
                'web'
            );
        }

        return redirect()
            ->route('taikhoan.danhsach')
            ->with('success', 'Đã cập nhật tài khoản thành công.');
    }

    /**
     * Khóa tài khoản nhân viên
     * Chỉ admin được truy cập (qua middleware role:admin)
     */
    public function khoa(User $user)
    {
        if ($this->thieuMigrationStage3()) {
            return $this->baoThieuMigration();
        }

        // Chỉ khóa nhân viên
        if (!in_array($user->role, ['admin', 'thungan', 'thukho'])) {
            abort(403, 'Chỉ được khóa tài khoản nhân viên.');
        }

        // Admin không tự khóa mình
        if ($user->id === Auth::id()) {
            return back()->with('error', 'Không thể tự khóa tài khoản.');
        }

        // Không khóa admin cuối cùng
        if ($user->role === 'admin') {
            $adminCount = User::where('role', 'admin')
                ->where('id', '!=', $user->id)
                ->where('khoa_luc', null)
                ->count();

            if ($adminCount === 0) {
                return back()->with('error', 'Không thể khóa admin cuối cùng.');
            }
        }

        $oldValues = ['khoa_luc' => $user->khoa_luc];
        $user->update(['khoa_luc' => now()]);

        NhatKyService::ghi(
            request(),
            'khoa',
            'users',
            $user->id,
            "Khóa tài khoản nhân viên: {$user->name}",
            $oldValues,
            ['khoa_luc' => now()->toDateTimeString()],
            'web'
        );

        return back()->with('success', 'Đã khóa tài khoản thành công.');
    }

    /**
     * Mở khóa tài khoản nhân viên
     * Chỉ admin được truy cập (qua middleware role:admin)
     */
    public function moKhoa(User $user)
    {
        if ($this->thieuMigrationStage3()) {
            return $this->baoThieuMigration();
        }

        // Chỉ mở khóa nhân viên
        if (!in_array($user->role, ['admin', 'thungan', 'thukho'])) {
            abort(403, 'Chỉ được mở khóa tài khoản nhân viên.');
        }

        $oldValues = ['khoa_luc' => $user->khoa_luc];
        $user->update(['khoa_luc' => null]);

        NhatKyService::ghi(
            request(),
            'mo_khoa',
            'users',
            $user->id,
            "Mở khóa tài khoản nhân viên: {$user->name}",
            $oldValues,
            ['khoa_luc' => null],
            'web'
        );

        return back()->with('success', 'Đã mở khóa tài khoản thành công.');
    }

    /**
     * Đặt lại mật khẩu tài khoản nhân viên
     * Chỉ admin được truy cập (qua middleware role:admin)
     */
    public function datLaiMatKhau(User $user)
    {
        if ($this->thieuMigrationStage3()) {
            return $this->baoThieuMigration();
        }

        // Chỉ đặt lại mật khẩu nhân viên
        if (!in_array($user->role, ['admin', 'thungan', 'thukho'])) {
            abort(403, 'Chỉ được đặt lại mật khẩu tài khoản nhân viên.');
        }

        // Mật khẩu tạm sinh mới
        $tempPassword = Str::password(16, true, true, true);

        $user->update([
            'password' => Hash::make($tempPassword),
            'bat_buoc_doi_mat_khau' => true, // Bắt buộc đổi ở lần đăng nhập đầu
        ]);

        NhatKyService::ghi(
            request(),
            'dat_lai_mat_khau',
            'users',
            $user->id,
            "Đặt lại mật khẩu tài khoản nhân viên: {$user->name}",
            ['bat_buoc_doi_mat_khau' => $user->getOriginal('bat_buoc_doi_mat_khau')],
            ['bat_buoc_doi_mat_khau' => true],
            'web'
        );

        // Mật khẩu tạm chỉ hiển thị trong flash session
        return back()
            ->with('temp_password', $tempPassword)
            ->with('success', 'Đã đặt lại mật khẩu. Mật khẩu tạm mới hiển thị bên dưới (chỉ hiện một lần).');
    }

    /**
     * Guard migration Stage 3: kiểm tra cột thật trong DB (cache tĩnh qua
     * KiemTraSchema) thay vì array_key_exists trên model rỗng — cách cũ luôn
     * trả false nên /taikhoan redirect vô hạn kể cả khi DB đã migrate.
     */
    private function thieuMigrationStage3(): bool
    {
        return ! KiemTraSchema::coCot('users', 'khoa_luc')
            || ! KiemTraSchema::coCot('users', 'bat_buoc_doi_mat_khau');
    }

    private function baoThieuMigration()
    {
        return back()->with('error', 'Cần áp migration Stage 3 trước khi sử dụng tính năng tài khoản.');
    }
}
