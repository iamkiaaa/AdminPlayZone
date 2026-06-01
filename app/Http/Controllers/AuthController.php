<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AuthController extends Controller
{
    private $apiUrl = 'https://sixties-pout-envoy.ngrok-free.dev/api';

    public function showLogin()
    {
        return session('admin_logged_in') ? redirect()->route('admin.dashboard') : view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate(['email' => 'required|email', 'password' => 'required']);

        try {
            $response = Http::post("{$this->apiUrl}/login", $request->only('email', 'password'));

            if ($response->successful()) {
                $data = $response->json();
                session([
                    'admin_logged_in' => true,
                    'admin_name' => $data['user']['name'] ?? 'Admin',
                    'admin_email' => $data['user']['email'] ?? $request->email,
                    'token' => $data['token'] ?? null,
                ]);
                return redirect()->route('admin.dashboard');
            }
            return back()->with('error', 'Email atau kata sandi salah.');
        } catch (\Exception $e) {
            return back()->with('error', 'Tidak bisa terhubung ke server API.');
        }
    }

    public function logout(Request $request)
    {
        try {
            if ($token = session('token')) {
                Http::withToken($token)->post("{$this->apiUrl}/logout");
            }
        } catch (\Exception $e) {}

        session()->flush();
        return redirect()->route('login');
    }
}