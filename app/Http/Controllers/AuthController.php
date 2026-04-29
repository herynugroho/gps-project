<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    // 1. Menampilkan Halaman Login
    public function showLogin()
    {
        return view('auth.login');
    }

    // 2. Memproses Data Login
    public function authenticate(Request $request)
    {
        // Validasi inputan tidak boleh kosong
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $remember = $request->has('remember');

        // Cek ke database, apakah email & password cocok?
        if (Auth::attempt($credentials, $remember)) {
            // Jika cocok, buat sesi aman baru (Mencegah serangan Hacker / Session Fixation)
            $request->session()->regenerate();

            // Arahkan ke dashboard GPS Bapak
            return redirect()->intended('/dashboard'); 
        }

        // Jika salah, kembalikan ke halaman login dengan pesan error
        return back()->withErrors([
            'email' => 'Email atau Password yang Anda masukkan salah.',
        ])->onlyInput('email');
    }

    // 3. Memproses Logout
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}