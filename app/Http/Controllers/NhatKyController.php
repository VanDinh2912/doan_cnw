<?php

namespace App\Http\Controllers;

use App\Models\NhatKyHoatDong;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NhatKyController extends Controller
{
    public function index(Request $request): View
    {
        return view('nhatky.index', [
            'nhatKys' => NhatKyHoatDong::with('user')
                ->when($request->filled('hanh_dong'), fn ($query) => $query->where('hanh_dong', $request->string('hanh_dong')))
                ->latest()->paginate(30)->withQueryString(),
        ]);
    }
}
