<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AdminAuthController extends Controller
{
    /*
    |------------------------------------------------------------------
    | POST /api/admin/login
    |------------------------------------------------------------------
    */
    public function login(Request $request)
    {
        // Honeypot
        if ($request->filled('website')) {
            return response()->json(['message' => 'OK'], 200);
        }

        // Rate limit — 10 req/min per IP
        $rateLimitKey = 'login_ip:' . $request->ip();
        if (RateLimiter::tooManyAttempts($rateLimitKey, 10)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            return response()->json(['message' => "Too many requests. Try again in {$seconds} seconds."], 429);
        }
        RateLimiter::hit($rateLimitKey, 60);

        $request->validate(['email' => 'required|email', 'password' => 'required|string']);

        // IP lockout
        $lockKey     = 'login_lock:' . $request->ip();
        $attemptsKey = 'login_attempts:' . $request->ip();
        $maxAttempts = 5;
        $lockSeconds = 120;

        if (Cache::has($lockKey)) {
            return response()->json([
                'message'      => 'Too many failed attempts. Please wait before trying again.',
                'locked_until' => now()->addSeconds(Cache::get($lockKey . '_ttl', 0))->timestamp * 1000,
            ], 429);
        }

        $admin = Admin::where('email', $request->email)->first();

        if (! $admin || ! Hash::check($request->password, $admin->password)) {
            $attempts = Cache::increment($attemptsKey);
            Cache::put($attemptsKey, $attempts, now()->addMinutes(10));
            if ($attempts >= $maxAttempts) {
                Cache::put($lockKey, true, now()->addSeconds($lockSeconds));
                Cache::put($lockKey . '_ttl', $lockSeconds, now()->addSeconds($lockSeconds));
                Cache::forget($attemptsKey);
                return response()->json([
                    'message'      => "Account locked for {$lockSeconds} seconds.",
                    'locked_until' => now()->addSeconds($lockSeconds)->timestamp * 1000,
                ], 429);
            }
            $remaining = $maxAttempts - $attempts;
            return response()->json([
                'message'   => "Invalid email or password. {$remaining} attempt(s) remaining.",
                'attempts'  => $attempts,
                'remaining' => $remaining,
            ], 401);
        }

        Cache::forget($attemptsKey);
        Cache::forget($lockKey);

        $deviceId       = $request->input('device_id', '');
        $trustedKey     = "trusted_devices:{$admin->id}";
        $trustedDevices = Cache::get($trustedKey, []);
        $isNewDevice    = ! in_array($deviceId, $trustedDevices);

        // Generate OTP
        $otp        = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $sessionKey = 'otp:' . Str::random(32);

        Cache::put($sessionKey, [
            'admin_id'   => $admin->id,
            'otp'        => $otp,
            'device_id'  => $deviceId,
            'new_device' => $isNewDevice,
        ], now()->addMinutes(10));

        $this->sendOtpEmail($admin, $otp);

        $parts     = explode('@', $admin->email);
        $masked    = substr($parts[0], 0, 1) . str_repeat('*', max(1, strlen($parts[0]) - 1));
        $emailHint = $masked . '@' . $parts[1];

        return response()->json([
            'requires_2fa' => true,
            'new_device'   => $isNewDevice,
            'token'        => $sessionKey,
            'email_hint'   => $emailHint,
        ]);
    }

    /*
    |------------------------------------------------------------------
    | POST /api/admin/verify-otp
    |------------------------------------------------------------------
    */
    public function verifyOtp(Request $request)
    {
        $request->validate(['token' => 'required|string', 'otp' => 'required|string|size:6']);

        $session = Cache::get($request->token);
        if (! $session) return response()->json(['message' => 'Code expired. Please log in again.'], 422);
        if ($session['otp'] !== $request->otp) return response()->json(['message' => 'Incorrect verification code.'], 422);

        Cache::forget($request->token);

        $admin = Admin::findOrFail($session['admin_id']);

        if ($request->boolean('trust') && $session['device_id']) {
            $trustedKey     = "trusted_devices:{$admin->id}";
            $trustedDevices = Cache::get($trustedKey, []);
            if (! in_array($session['device_id'], $trustedDevices)) {
                $trustedDevices[] = $session['device_id'];
            }
            Cache::put($trustedKey, $trustedDevices, now()->addDays(30));
        }

        $token = $admin->createToken('admin-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => ['id' => $admin->id, 'name' => $admin->name, 'email' => $admin->email],
        ]);
    }

    /*
    |------------------------------------------------------------------
    | POST /api/admin/resend-otp
    |------------------------------------------------------------------
    */
    public function resendOtp(Request $request)
    {
        $request->validate(['token' => 'required|string']);
        $session = Cache::get($request->token);
        if (! $session) return response()->json(['message' => 'Session expired. Please log in again.'], 422);

        $admin          = Admin::findOrFail($session['admin_id']);
        $otp            = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $session['otp'] = $otp;
        Cache::put($request->token, $session, now()->addMinutes(10));

        $this->sendOtpEmail($admin, $otp);

        return response()->json(['message' => 'A new code has been sent.']);
    }

    /*
    |------------------------------------------------------------------
    | POST /api/admin/forgot-password
    |------------------------------------------------------------------
    */
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        // Always return success — don't reveal if email exists
        $admin = Admin::where('email', $request->email)->first();

        if ($admin) {
            $token      = Str::random(64);
            $cacheKey   = "password_reset:{$token}";
            Cache::put($cacheKey, $admin->id, now()->addMinutes(30));

            $resetUrl = config('app.frontend_url', 'http://localhost:5173') . "/admin/reset-password?token={$token}";

            Mail::send([], [], function ($message) use ($admin, $resetUrl) {
                $message->to($admin->email, $admin->name)
                    ->subject('Reset Your Admin Password — SRES')
                    ->html("
                        <div style='font-family:Georgia,serif;max-width:480px;margin:0 auto;border:1.5px solid #0a1f52;'>
                            <div style='background:#0a1f52;padding:20px 24px;'>
                                <p style='color:#f5c518;font-size:11px;font-weight:800;letter-spacing:0.2em;text-transform:uppercase;margin:0;'>
                                    San Roque Elementary School
                                </p>
                            </div>
                            <div style='padding:32px 24px;'>
                                <h2 style='color:#0a1f52;font-size:18px;margin:0 0 16px;'>Password Reset Request</h2>
                                <p style='font-size:14px;color:#3a3a3a;line-height:1.7;margin:0 0 24px;'>
                                    Click the button below to reset your admin password. This link expires in <strong>30 minutes</strong>.
                                </p>
                                <a href='{$resetUrl}' style='display:inline-block;background:#0a1f52;color:#f5c518;padding:14px 32px;font-size:12px;font-weight:800;letter-spacing:0.16em;text-transform:uppercase;text-decoration:none;'>
                                    Reset Password →
                                </a>
                                <p style='margin:24px 0 0;font-size:12px;color:#888;line-height:1.7;'>
                                    If you did not request this, ignore this email. Your password will not change.
                                </p>
                            </div>
                        </div>
                    ");
            });
        }

        return response()->json(['message' => 'If that email is registered, a reset link has been sent.']);
    }

    /*
    |------------------------------------------------------------------
    | POST /api/admin/reset-password
    |------------------------------------------------------------------
    */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token'    => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $cacheKey = "password_reset:{$request->token}";
        $adminId  = Cache::get($cacheKey);

        if (! $adminId) {
            return response()->json(['message' => 'This reset link is invalid or has expired.'], 422);
        }

        $admin = Admin::findOrFail($adminId);
        $admin->update(['password' => Hash::make($request->password)]);
        $admin->tokens()->delete(); // revoke all sessions
        Cache::forget($cacheKey);

        return response()->json(['message' => 'Password reset successfully. Please log in.']);
    }

    /*
    |------------------------------------------------------------------
    | POST /api/admin/logout
    |------------------------------------------------------------------
    */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out.']);
    }

    /*
    |------------------------------------------------------------------
    | GET /api/admin/me
    |------------------------------------------------------------------
    */
    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    /*
    |------------------------------------------------------------------
    | GET /api/admin/stats  ← FIXED: Student::count() and Staff::count()
    |------------------------------------------------------------------
    */
    public function stats()
    {
        return response()->json([
            'enrollments' => Enrollment::count(),
            'pending'     => Enrollment::where('status', 'pending')->count(),
            'approved'    => Enrollment::where('status', 'approved')->count(),
            'rejected'    => Enrollment::where('status', 'rejected')->count(),
            'students'    => Student::count(),
            'faculty'     => Staff::count(),
        ]);
    }

    /*
    |------------------------------------------------------------------
    | Send OTP email
    |------------------------------------------------------------------
    */
    private function sendOtpEmail(Admin $admin, string $otp): void
    {
        Mail::send([], [], function ($message) use ($admin, $otp) {
            $message->to($admin->email, $admin->name)
                ->subject('Your Admin Login Code — SRES')
                ->html("
                    <div style='font-family:Georgia,serif;max-width:480px;margin:0 auto;border:1.5px solid #0a1f52;'>
                        <div style='background:#0a1f52;padding:20px 24px;'>
                            <p style='color:#f5c518;font-size:11px;font-weight:800;letter-spacing:0.2em;text-transform:uppercase;margin:0;'>
                                San Roque Elementary School
                            </p>
                        </div>
                        <div style='padding:32px 24px;text-align:center;'>
                            <p style='font-size:14px;color:#3a3a3a;margin:0 0 24px;line-height:1.7;'>
                                Your admin login verification code is:
                            </p>
                            <div style='background:#0a1f52;display:inline-block;padding:16px 40px;margin-bottom:24px;'>
                                <span style='font-size:32px;font-weight:900;color:#f5c518;letter-spacing:0.3em;font-family:monospace;'>
                                    {$otp}
                                </span>
                            </div>
                            <p style='font-size:12px;color:#888;margin:0;line-height:1.7;'>
                                This code expires in <strong>10 minutes</strong>.<br>
                                If you did not attempt to log in, please secure your account immediately.
                            </p>
                        </div>
                    </div>
                ");
        });
    }
}