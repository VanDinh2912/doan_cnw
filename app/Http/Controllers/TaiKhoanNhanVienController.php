<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class TaiKhoanNhanVienController extends Controller
{
    /**
     * Danh sách tài khoản nhân viên (admin, thungan, thukho)
     * Chỉ admin được truy cập
     */
    public function index(Request $request)
    {
        $this->authorize('admin');

        // Guard: Kiểm tra xem migration đã áp chưa
        if (!array_key_exists('khoa_luc', (new User())->getAttributes())) {
            return back()->with('error', 'Cần áp migration Stage 3 trước khi sử dụng tính năng tài khoản.');
        }

        $query = User::whereIn('role', ['admin', 'thungan', 'thukho'])
            ->with('customer'); // Eager load để tránh N+1

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
     * Chỉ admin được truy cập
     */
    public function create()
    {
        $this->authorize('admin');

        return view('taikhoan.them');
    }

    /**
     * Lưu tài khoản nhân viên mới
     * Chỉ admin được truy cập
     */
    public function store(Request $request)
    {
        $this->authorize('admin');

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
        // activity_logs sẽ được ghi tự động qua observer/trait

        return redirect()
            ->route('taikhoan.danhsach')
            ->with('success', "Đã tạo tài khoản thành công. Mật khẩu tạm: {$tempPassword} (chỉ hiển thị một lần)");
    }

    /**
     * Form sửa tài khoản nhân viên
     * Chỉ admin được truy cập
     */
    public function edit(User $user)
    {
        $this->authorize('admin');

        // Chỉ cho phép sửa nhân viên, không sửa khách hàng
        if (!in_array($user->role, ['admin', 'thungan', 'thukho'])) {
            abort(403, 'Chỉ được sửa tài khoản nhân viên.');
        }

        return view('taikhoan.sua', compact('user'));
    }

    /**
     * Cập nhật tài khoản nhân viên
     * Chỉ admin được truy cập
     */
    public function update(Request $request, User $user)
    {
        $this->authorize('admin');

        // Chỉ cho phép sửa nhân viên
        if (!in_array($user->role, ['admin', 'thungan', 'thukho'])) {
            abort(403, 'Chỉ được sửa tài khoản nhân viên.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'role' => 'required|in:admin,thungan,thukho',
        ]);

        // QUY TẮC BẮT BUỘC:
        // 1. Admin không tự khóa mình
        if ($user->id === Auth::id() && array_key_exists('khoa_luc', $user->getAttributes()) && $user->khoa_luc !== null) {
            return back()->with('error', 'Không thể tự sửa tài khoản đã bị khóa.');
        }

        // 2. Admin không tự hạ vai trò mình
        if ($user->id === Auth::id() && $user->role === 'admin' && $validated['role'] !== 'admin') {
            return back()->with('error', 'Không thể tự hạ vai trò admin.');
        }

        // 3. Luôn còn ít nhất một admin hoạt động
        if ($user->role === 'admin' && $validated['role'] !== 'admin') {
            $query = User::where('role', 'admin')->where('id', '!=', $user->id);
            if (array_key_exists('khoa_luc', (new User())->getAttributes())) {
                $query->where('khoa_luc', null);
            }
            $adminCount = $query->count();

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

        $user->update($validated);

        return redirect()
            ->route('taikhoan.danhsach')
            ->with('success', 'Đã cập nhật tài khoản thành công.');
    }

    /**
     * Khóa tài khoản nhân viên
     * Chỉ admin được truy cập
     */
    public function khoa(User $user)
    {
        $this->authorize('admin');

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
            $query = User::where('role', 'admin')->where('id', '!=', $user->id);
            if (array_key_exists('khoa_luc', (new User())->getAttributes())) {
                $query->where('khoa_luc', null);
            }
            $adminCount = $query->count();

            if ($adminCount === 0) {
                return back()->with('error', 'Không thể khóa admin cuối cùng.');
            }
        }

        $user->update(['khoa_luc' => now()]);

        return back()->with('success', 'Đã khóa tài khoản thành công.');
    }

    /**
     * Mở khóa tài khoản nhân viên
     * Chỉ admin được truy cập
     */
    public function moKhoa(User $user)
    {
        $this->authorize('admin');

        // Chỉ mở khóa nhân viên
        if (!in_array($user->role, ['admin', 'thungan', 'thukho'])) {
            abort(403, 'Chỉ được mở khóa tài khoản nhân viên.');
        }

        $user->update(['khoa_luc' => null]);

        return back()->with('success', 'Đã mở khóa tài khoản thành công.');
    }

    /**
     * Đặt lại mật khẩu tài khoản nhân viên
     * Chỉ admin được truy cập
     */
    public function datLaiMatKhau(User $user)
    {
        $this->authorize('admin');

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

        return back()->with('success', "Đã đặt lại mật khẩu. Mật khẩu tạm mới: {$tempPassword} (chỉ hiển thị một lần)");
    }
}
