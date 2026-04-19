<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\ActivityLog;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SettingsController extends Controller
{
    /*
    |------------------------------------------------------------------
    | Helper — write an activity log entry
    |------------------------------------------------------------------
    */
    private function log(Request $request, string $action, string $description): void
    {
        $admin = $request->user();
        ActivityLog::create([
            'admin_id'    => $admin?->id,
            'action'      => $action,
            'description' => $description,
            'ip'          => $request->ip(),
        ]);
    }

    /*
    |------------------------------------------------------------------
    | GET /api/settings  (PUBLIC)
    |------------------------------------------------------------------
    */
    public function publicSettings(): JsonResponse
    {
        $settings = $this->loadSettings();

        $maintenancePages = $settings['maintenance_pages'] ?? '[]';
        if (is_string($maintenancePages)) {
            $maintenancePages = json_decode($maintenancePages, true) ?? [];
        }

        return response()->json([
            'enrollment_open'     => $this->toBool($settings['enrollment_open']  ?? 'true'),
            'maintenance_mode'    => $this->toBool($settings['maintenance_mode'] ?? 'false'),
            'maintenance_pages'   => $maintenancePages,
            'school_year'         => $settings['school_year']         ?? $settings['enrollment_year'] ?? '2025–2026',
            'announcement_ticker' => $settings['announcement_ticker'] ?? '',
            'school_name'         => $settings['school_name']         ?? 'San Roque Elementary School',
            'school_tagline'      => $settings['school_tagline']      ?? 'DepEd · Division of Catanduanes',
            'school_email'        => $settings['school_email']        ?? '113330@deped.gov.ph',
            'school_phone'        => $settings['school_phone']        ?? '+63 9605519104',
            'school_address'      => $settings['school_address']      ?? 'San Roque, Viga, Catanduanes, Philippines',
        ]);
    }

    /*
    |------------------------------------------------------------------
    | POST  /api/admin/profile  — photo upload (multipart)
    | PATCH /api/admin/profile  — name / email / bio (JSON)
    |------------------------------------------------------------------
    */
    public function updateProfile(Request $request): JsonResponse
    {
        $admin = $request->user();

        /* ── Photo upload ── */
        if ($request->hasFile('profile_photo')) {
            $request->validate(['profile_photo' => 'required|image|max:2048']);

            $path = $request->file('profile_photo')->store('admin_photos', 'public');
            $admin->update(['profile_photo' => $path]);

            $this->log($request, 'profile_photo_updated', 'Admin updated their profile photo.');

            return response()->json([
                'message' => 'Profile photo updated.',
                'user'    => $this->adminData($admin),
            ]);
        }

        /* ── Text fields ── */
        $request->validate([
            'name'  => 'required|string|max:100',
            'email' => 'required|email|max:150|unique:admins,email,' . $admin->id,
            'bio'   => 'nullable|string|max:500',
        ]);

        $old = $admin->only('name', 'email', 'bio');
        $admin->update($request->only('name', 'email', 'bio'));

        $changes = [];
        if ($old['name']          !== $admin->name)  $changes[] = "name → \"{$admin->name}\"";
        if ($old['email']         !== $admin->email) $changes[] = "email → \"{$admin->email}\"";
        if (($old['bio'] ?? '') !== ($admin->bio ?? '')) $changes[] = 'bio';

        $desc = count($changes)
            ? 'Admin updated profile: ' . implode(', ', $changes) . '.'
            : 'Admin saved profile (no changes).';

        $this->log($request, 'profile_updated', $desc);

        return response()->json([
            'message' => 'Profile updated.',
            'user'    => $this->adminData($admin),
        ]);
    }

    /*
    |------------------------------------------------------------------
    | PATCH /api/admin/password
    |------------------------------------------------------------------
    */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password'      => 'required|string',
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string',
        ]);

        $admin = $request->user();

        if (! Hash::check($request->current_password, $admin->password)) {
            return response()->json(['message' => 'Current password is incorrect.'], 422);
        }

        $admin->update(['password' => Hash::make($request->password)]);

        $currentId = $admin->currentAccessToken()->id;
        $admin->tokens()->where('id', '!=', $currentId)->delete();

        $this->log($request, 'password_changed', 'Admin changed their password.');

        return response()->json(['message' => 'Password changed successfully.']);
    }

    /*
    |------------------------------------------------------------------
    | POST /api/admin/forgot-password  (PUBLIC)
    |------------------------------------------------------------------
    */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $admin = Admin::where('email', $request->email)->first();

        if (! $admin) {
            return response()->json(['message' => 'If that email is registered, a reset link has been sent.']);
        }

        $token = Str::random(64);
        DB::table('password_resets')->where('email', $admin->email)->delete();
        DB::table('password_resets')->insert([
            'email'      => $admin->email,
            'token'      => Hash::make($token),
            'created_at' => Carbon::now(),
        ]);

        $resetUrl = config('app.frontend_url', 'http://localhost:5173')
            . '/admin/reset-password?token=' . $token;

        Mail::send([], [], function ($m) use ($admin, $resetUrl) {
            $m->to($admin->email, $admin->name)
              ->subject('Reset Your SRES Admin Password')
              ->html("
                <div style='font-family:Georgia,serif;max-width:480px;margin:0 auto;border:1.5px solid #0a1f52;'>
                  <div style='background:#0a1f52;padding:20px 24px;'>
                    <p style='color:#f5c518;font-size:11px;font-weight:800;letter-spacing:0.2em;text-transform:uppercase;margin:0;'>SRES Admin Panel — Password Reset</p>
                  </div>
                  <div style='padding:32px 24px;'>
                    <p style='font-size:14px;color:#1a1a1a;margin:0 0 16px;'>Hi {$admin->name},</p>
                    <p style='font-size:13px;color:#3a3a3a;margin:0 0 24px;line-height:1.7;'>Click the button below to reset your password. This link expires in <strong>60 minutes</strong>.</p>
                    <div style='text-align:center;margin-bottom:24px;'>
                      <a href='{$resetUrl}' style='display:inline-block;background:#0a1f52;color:#f5c518;padding:14px 36px;text-decoration:none;font-size:12px;font-weight:800;letter-spacing:0.14em;text-transform:uppercase;'>Reset My Password</a>
                    </div>
                    <p style='font-size:11px;color:#888;'>If you didn't request this, ignore this email.</p>
                    <p style='font-size:11px;color:#aaa;margin:16px 0 0;word-break:break-all;'>Or copy: {$resetUrl}</p>
                  </div>
                  <div style='background:#f8fafc;padding:14px 24px;border-top:1px solid #e2e8f0;'>
                    <p style='margin:0;font-size:10px;color:#94a3b8;'>San Roque Elementary School · Admin Portal</p>
                  </div>
                </div>
              ");
        });

        ActivityLog::create([
            'admin_id'    => $admin->id,
            'action'      => 'forgot_password_requested',
            'description' => "Password reset link sent to {$admin->email}.",
            'ip'          => $request->ip(),
        ]);

        return response()->json(['message' => 'Password reset link sent to ' . $admin->email . '.']);
    }

    /*
    |------------------------------------------------------------------
    | POST /api/admin/reset-password  (PUBLIC)
    |------------------------------------------------------------------
    */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token'                 => 'required|string',
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string',
        ]);

        $records = DB::table('password_resets')->orderBy('created_at', 'desc')->get();

        $record = null;
        foreach ($records as $row) {
            if (Hash::check($request->token, $row->token)) { $record = $row; break; }
        }

        if (! $record) {
            return response()->json(['message' => 'This reset link is invalid or has already been used.'], 422);
        }

        if (Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_resets')->where('email', $record->email)->delete();
            return response()->json(['message' => 'This reset link has expired. Please request a new one.'], 422);
        }

        $admin = Admin::where('email', $record->email)->first();
        if (! $admin) {
            return response()->json(['message' => 'Admin account not found.'], 422);
        }

        $admin->update(['password' => Hash::make($request->password)]);
        $admin->tokens()->delete();
        DB::table('password_resets')->where('email', $record->email)->delete();

        ActivityLog::create([
            'admin_id'    => $admin->id,
            'action'      => 'password_reset',
            'description' => 'Admin reset their password via email link.',
            'ip'          => $request->ip(),
        ]);

        return response()->json(['message' => 'Password reset successfully. Please log in with your new password.']);
    }

    /*
    |------------------------------------------------------------------
    | POST /api/admin/2fa/test
    |------------------------------------------------------------------
    */
    public function testOtp(Request $request): JsonResponse
    {
        $admin = $request->user();
        $otp   = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Mail::send([], [], function ($m) use ($admin, $otp) {
            $m->to($admin->email, $admin->name)
              ->subject('Test OTP — SRES Admin Panel')
              ->html("
                <div style='font-family:Georgia,serif;max-width:480px;margin:0 auto;border:1.5px solid #0a1f52;'>
                  <div style='background:#0a1f52;padding:20px 24px;'>
                    <p style='color:#f5c518;font-size:11px;font-weight:800;letter-spacing:0.2em;text-transform:uppercase;margin:0;'>SRES Admin Panel — Test OTP</p>
                  </div>
                  <div style='padding:32px 24px;text-align:center;'>
                    <p style='font-size:14px;color:#3a3a3a;margin:0 0 20px;'>This is a test OTP. Your 2FA is working correctly.</p>
                    <div style='background:#0a1f52;display:inline-block;padding:16px 40px;margin-bottom:20px;'>
                      <span style='font-size:32px;font-weight:900;color:#f5c518;letter-spacing:0.3em;font-family:monospace;'>{$otp}</span>
                    </div>
                    <p style='font-size:12px;color:#888;'>This code is for testing only and will not grant access.</p>
                  </div>
                </div>
              ");
        });

        $this->log($request, 'otp_test_sent', 'Admin sent a test OTP to their email.');

        return response()->json(['message' => 'Test OTP sent to ' . $admin->email]);
    }

    /*
    |------------------------------------------------------------------
    | GET /api/admin/site-settings
    |------------------------------------------------------------------
    */
    public function getSiteSettings(): JsonResponse
    {
        $settings = $this->loadSettings();

        foreach (['enrollment_open', 'maintenance_mode', 'two_fa_enabled'] as $key) {
            if (isset($settings[$key])) {
                $settings[$key] = $this->toBool($settings[$key]);
            }
        }

        $mp = $settings['maintenance_pages'] ?? '[]';
        $settings['maintenance_pages'] = is_string($mp)
            ? (json_decode($mp, true) ?? [])
            : (is_array($mp) ? $mp : []);

        return response()->json($settings);
    }

    /*
    |------------------------------------------------------------------
    | POST /api/admin/site-settings
    |------------------------------------------------------------------
    */
    public function saveSiteSettings(Request $request): JsonResponse
    {
        $request->validate([
            'school_name'         => 'sometimes|string|max:200',
            'school_tagline'      => 'nullable|string|max:200',
            'school_email'        => 'nullable|email|max:150',
            'school_phone'        => 'nullable|string|max:50',
            'school_address'      => 'nullable|string|max:300',
            'enrollment_open'     => 'sometimes|boolean',
            'enrollment_year'     => 'nullable|string|max:20',
            'announcement_ticker' => 'nullable|string|max:300',
            'maintenance_mode'    => 'sometimes|boolean',
            'maintenance_pages'   => 'sometimes',
            'two_fa_enabled'      => 'sometimes|boolean',
        ]);

        $allowed = [
            'school_name', 'school_tagline', 'school_email', 'school_phone',
            'school_address', 'enrollment_open', 'enrollment_year',
            'announcement_ticker', 'maintenance_mode', 'maintenance_pages', 'two_fa_enabled',
        ];

        $data = $request->only($allowed);

        foreach ($data as $key => $value) {
            if ($key === 'maintenance_pages') {
                $value = is_array($value) ? json_encode(array_values($value)) : '[]';
            } elseif (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            SiteSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        if (isset($data['enrollment_year'])) {
            SiteSetting::updateOrCreate(['key' => 'school_year'], ['value' => $data['enrollment_year']]);
        }

        $this->log($request, 'site_settings_saved', 'Admin updated site settings.');

        return response()->json([
            'message'  => 'Settings saved.',
            'settings' => $this->getSiteSettings()->getData(true),
        ]);
    }

    /*
    |------------------------------------------------------------------
    | GET /api/admin/logs
    |------------------------------------------------------------------
    */
    public function getLogs(Request $request): JsonResponse
    {
        $query = ActivityLog::orderBy('created_at', 'desc');

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        $paginated = $query->paginate(20);

        return response()->json($paginated);
    }

    /*
    |------------------------------------------------------------------
    | Helpers
    |------------------------------------------------------------------
    */
    private function adminData(Admin $admin): array
    {
        return [
            'id'            => $admin->id,
            'name'          => $admin->name,
            'email'         => $admin->email,
            'bio'           => $admin->bio,
            'profile_photo' => $admin->profile_photo,
        ];
    }

    private function loadSettings(): array
    {
        return array_merge(
            $this->defaultSiteSettings(),
            SiteSetting::all()->pluck('value', 'key')->toArray()
        );
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) return $value;
        return in_array($value, [true, 1, '1', 'true', 'yes'], true);
    }

    private function defaultSiteSettings(): array
    {
        return [
            'school_name'         => 'San Roque Elementary School',
            'school_tagline'      => 'DepEd · Division of Catanduanes',
            'school_email'        => '113330@deped.gov.ph',
            'school_phone'        => '+63 9605519104',
            'school_address'      => 'San Roque, Viga, Catanduanes, Philippines',
            'enrollment_open'     => 'true',
            'enrollment_year'     => '2025–2026',
            'announcement_ticker' => '',
            'maintenance_mode'    => 'false',
            'maintenance_pages'   => '[]',
            'school_year'         => '2025–2026',
            'two_fa_enabled'      => 'true',
        ];
    }
}