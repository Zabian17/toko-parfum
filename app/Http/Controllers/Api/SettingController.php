<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class SettingController extends Controller
{
    public function index(): JsonResponse
    {
        $settings = Setting::orderBy('group')->orderBy('key')->get();

        return response()->json(['data' => $settings]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->all();
        foreach ($data as $key => $value) {
            if (!str_starts_with((string) $key, '_')) {
                Setting::set($key, $value);
            }
        }

        return response()->json(['message' => 'Settings updated.']);
    }

    public function toggle(Request $request, string $key): JsonResponse
    {
        $request->validate([
            'value' => ['required', 'boolean'],
        ]);

        $allowedKeys = [
            'rajaongkir_enabled',
            'midtrans_enabled',
            'email_notification_enabled',
            'maintenance_mode',
        ];

        if (!in_array($key, $allowedKeys)) {
            return response()->json(['message' => 'Unknown setting key.'], 422);
        }

        Setting::set($key, $request->boolean('value'));

        return response()->json([
            'message' => "Setting '{$key}' updated.",
            'value'   => $request->boolean('value'),
        ]);
    }

    /**
     * Send a test email to the admin_notification_email setting.
     */
    public function testEmail(Request $request): JsonResponse
    {
        $to = Setting::get('admin_notification_email');

        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['message' => 'Email notifikasi admin belum diatur atau tidak valid.'], 422);
        }

        try {
            $subject = 'Tes Notifikasi Email — Toko Albi Parfum';
            $body = "Ini adalah email pengujian dari Toko Albi Parfum. Jika Anda menerima ini, konfigurasi email berfungsi dengan baik.";

            Mail::raw($body, function ($message) use ($to, $subject) {
                $message->to($to)->subject($subject);
            });

            return response()->json(['message' => 'Email pengujian berhasil dikirim.']);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Gagal mengirim email pengujian: ' . $e->getMessage()], 500);
        }
    }
}
