<?php

use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\ContentController;
use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\GalleryController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\AdminAuthController;
use App\Http\Controllers\Api\StudentController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\Admin\StatsController;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::get('/announcements',                [AnnouncementController::class, 'publicIndex']);
Route::get('/announcements/{announcement}', [AnnouncementController::class, 'publicShow']);

Route::get('/news',                [AnnouncementController::class, 'publicNewsIndex']);
Route::get('/news/{announcement}', [AnnouncementController::class, 'publicNewsShow']);

Route::get('/announcement-categories', [AnnouncementController::class, 'publicCategories']);

Route::get('/gallery',                                 [GalleryController::class, 'index']);
Route::get('/gallery/{album}',                         [GalleryController::class, 'show']);
Route::get('/gallery/{album}/download',                [GalleryController::class, 'downloadAlbumZip']);
Route::get('/gallery/{album}/photos/{photo}/download', [GalleryController::class, 'downloadPhoto']);

Route::get('/staff', [StaffController::class, 'index']);

Route::get('/contents',        [ContentController::class, 'index']);
Route::get('/contents/{slug}', [ContentController::class, 'show']);

Route::post('/enroll',  [EnrollmentController::class, 'store'])
    ->middleware('throttle:enroll');

Route::post('/contact', [ContactController::class, 'store'])
    ->middleware('throttle:contact');

// Public settings — used by SettingsProvider in the frontend
Route::get('/settings', [SettingsController::class, 'publicSettings']);

/*
|--------------------------------------------------------------------------
| Admin Auth — PUBLIC (no sanctum guard)
|--------------------------------------------------------------------------
*/
Route::post('/admin/login',          [AdminAuthController::class, 'login']);
Route::post('/admin/verify-otp',     [AdminAuthController::class, 'verifyOtp']);
Route::post('/admin/resend-otp',     [AdminAuthController::class, 'resendOtp']);

// Forgot / reset password — must stay PUBLIC so unauthenticated admins can use them
Route::post('/admin/forgot-password', [SettingsController::class, 'forgotPassword']);
Route::post('/admin/reset-password',  [SettingsController::class, 'resetPassword']);

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok'
    ]);
});

/*
|--------------------------------------------------------------------------
| Admin Routes — PROTECTED (sanctum)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->prefix('admin')->group(function () {

    // Auth
    Route::post('/logout', [AdminAuthController::class, 'logout']);
    Route::get('/me',      [AdminAuthController::class, 'me']);
    Route::get('/stats', [StatsController::class, 'index']);

    // ── Profile ──────────────────────────────────────────────────────────
    Route::post('/profile',  [SettingsController::class, 'updateProfile']); // multipart photo upload
    Route::patch('/profile', [SettingsController::class, 'updateProfile']); // JSON name/email/bio

    // ── Password & 2FA ───────────────────────────────────────────────────
    Route::patch('/password',     [SettingsController::class, 'changePassword']);
    Route::post('/2fa/test',      [SettingsController::class, 'testOtp']);

    // ── Site Settings ────────────────────────────────────────────────────
    Route::get('/site-settings',  [SettingsController::class, 'getSiteSettings']);
    Route::post('/site-settings', [SettingsController::class, 'saveSiteSettings']);

    // ── Activity Logs ────────────────────────────────────────────────────
    Route::get('/logs', [SettingsController::class, 'getLogs']);

    // ── Announcements & News ─────────────────────────────────────────────
    Route::get('/announcements',                   [AnnouncementController::class, 'index']);
    Route::post('/announcements',                  [AnnouncementController::class, 'store']);
    Route::get('/announcements/{announcement}',    [AnnouncementController::class, 'show']);
    Route::put('/announcements/{announcement}',    [AnnouncementController::class, 'update']);
    Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy']);

    // ── Announcement Categories ───────────────────────────────────────────
    Route::get('/announcement-categories',          [AnnouncementController::class, 'categoriesIndex']);
    Route::post('/announcement-categories',         [AnnouncementController::class, 'categoriesStore']);
    Route::put('/announcement-categories/{cat}',    [AnnouncementController::class, 'categoriesUpdate']);
    Route::delete('/announcement-categories/{cat}', [AnnouncementController::class, 'categoriesDestroy']);

    // ── Gallery — Albums ─────────────────────────────────────────────────
    Route::get('/albums',            [GalleryController::class, 'adminIndex']);
    Route::post('/albums',           [GalleryController::class, 'adminStore']);
    Route::put('/albums/{album}',    [GalleryController::class, 'adminUpdate']);
    Route::post('/albums/{album}',   [GalleryController::class, 'adminUpdate']);
    Route::delete('/albums/{album}', [GalleryController::class, 'adminDestroy']);

    // ── Gallery — Photos ─────────────────────────────────────────────────
    Route::get('/albums/{album}/photos',                 [GalleryController::class, 'photosIndex']);
    Route::post('/albums/{album}/photos',                [GalleryController::class, 'photosStore']);
    Route::delete('/albums/{album}/photos/{photo}',      [GalleryController::class, 'photosDestroy']);
    Route::patch('/albums/{album}/photos/{photo}/cover', [GalleryController::class, 'photosSetCover']);

    // ── Contact Messages ─────────────────────────────────────────────────
    Route::prefix('contact-messages')->group(function () {
        Route::get('counts',                     [ContactController::class, 'counts']);
        Route::get('/',                          [ContactController::class, 'index']);
        Route::get('{contactMessage}',           [ContactController::class, 'show']);
        Route::post('{contactMessage}/reply',    [ContactController::class, 'reply']);
        Route::patch('{contactMessage}/replied', [ContactController::class, 'markReplied']);
        Route::delete('{contactMessage}',        [ContactController::class, 'destroy']);
    });

    // ── Enrollments ──────────────────────────────────────────────────────
    Route::get('/enrollments',               [EnrollmentController::class, 'index']);
    Route::get('/enrollments/{id}',          [EnrollmentController::class, 'show']);
    Route::patch('/enrollments/{id}/status', [EnrollmentController::class, 'updateStatus']);

    // ── Students ─────────────────────────────────────────────────────────
    Route::get('/students',               [StudentController::class, 'index']);
    Route::get('/students/{id}',          [StudentController::class, 'show']);
    Route::post('/students/{id}/photo',   [StudentController::class, 'uploadPhoto']);
    Route::delete('/students/{id}/photo', [StudentController::class, 'deletePhoto']);
    Route::patch('/students/{id}/status', [StudentController::class, 'updateStatus']);

    // ── Staff ────────────────────────────────────────────────────────────
    Route::get('/staff',                  [StaffController::class, 'adminIndex']);
    Route::post('/staff',                 [StaffController::class, 'store']);
    Route::put('/staff/{staff}',          [StaffController::class, 'update']);
    Route::delete('/staff/{staff}',       [StaffController::class, 'destroy']);
    Route::post('/staff/{staff}/photo',   [StaffController::class, 'uploadPhoto']);
    Route::delete('/staff/{staff}/photo', [StaffController::class, 'deletePhoto']);
});