<?php

use App\Http\Controllers\AccessoriesController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\BillingPrintController;
use App\Http\Controllers\BookingDetailController;
use App\Http\Controllers\BookingsController;
use App\Http\Controllers\CalendarDataController;
use App\Http\Controllers\ClientDocumentsController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CePreviewController;
use App\Http\Controllers\ChecklistController;
use App\Http\Controllers\ClientBookingDetailController;
use App\Http\Controllers\ClientsController;
use App\Http\Controllers\CostEstimatesController;
use App\Http\Controllers\CrewController;
use App\Http\Controllers\CrewDataController;
use App\Http\Controllers\CrewPortalController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\EquipmentDataController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\IncidentsController;
use App\Http\Controllers\PaymentReceiptController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfitLossController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\FavoritesController;
use App\Http\Controllers\FieldRequestsController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\RepairPurchaseController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\SupportChatController;
use App\Http\Controllers\DataRetentionController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\TileProxyController;
use App\Http\Controllers\TransportController;
use Illuminate\Support\Facades\Route;

Route::match(['get', 'post'], '/', [HomeController::class, 'index'])->name('home');

Route::get('/support-attachment/{messageId}', [HomeController::class, 'supportAttachment'])
    ->middleware(['auth'])
    ->whereNumber('messageId')
    ->name('support-attachment');

Route::get('/support-poll', [HomeController::class, 'supportPoll'])
    ->middleware(['auth'])
    ->name('support-poll');

Route::controller(AuthController::class)->group(function () {
    Route::get('/login', 'showLogin')->name('login');
    Route::post('/login', 'login')->name('login.store');
    Route::post('/register', 'register')->name('register.store');
    Route::get('/verify-mfa', 'showVerifyMfa')->name('verify-mfa');
    Route::post('/verify-mfa', 'verifyMfa')->name('verify-mfa.store');
    Route::get('/verify-signup', 'showVerifySignup')->name('verify-signup');
    Route::post('/verify-signup', 'verifySignup')->name('verify-signup.store');
    Route::get('/forgot-password', 'showForgotPassword')->name('forgot-password');
    Route::post('/forgot-password', 'sendResetOtp')->name('forgot-password.store');
    Route::get('/reset-password', 'showResetPassword')->name('reset-password');
    Route::post('/reset-password', 'resetPassword')->name('reset-password.store');
    Route::get('/logout', 'logout')->name('logout');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'can_access:dashboard'])
    ->name('dashboard');

Route::match(['get', 'post'], '/bookings', [BookingsController::class, 'index'])
    ->middleware(['auth', 'can_access:bookings'])
    ->name('bookings');

Route::get('/bookings/{id}', [BookingDetailController::class, 'show'])
    ->middleware(['auth', 'can_access:bookings'])
    ->whereNumber('id')
    ->name('booking-detail');

Route::post('/bookings/{id}', [BookingDetailController::class, 'act'])
    ->middleware(['auth', 'can_access:bookings'])
    ->whereNumber('id')
    ->name('booking-detail.act');

// Profit & Loss — Part 13. Deliberately not open to traffic: this is owner/finance data.
Route::get('/profit-loss', [ProfitLossController::class, 'index'])
    ->middleware(['auth', 'can_access:profit_loss'])
    ->name('profit-loss');

// Calendar Data — Part 17. An analytics read of the shoot calendar, distinct from the
// booking-creation calendar widget on the Dashboard.
Route::get('/calendar-data', [CalendarDataController::class, 'index'])
    ->middleware(['auth', 'can_access:calendar_data'])
    ->name('calendar-data');

// Analytics pages split out of Reports — Part 11.
Route::get('/crew-data', [CrewDataController::class, 'index'])
    ->middleware(['auth', 'can_access:crew_data'])
    ->name('crew-data');

Route::get('/equipment-data', [EquipmentDataController::class, 'index'])
    ->middleware(['auth', 'can_access:equipment_data'])
    ->name('equipment-data');

// All cost estimates across bookings — Part 10.
Route::get('/cost-estimates', [CostEstimatesController::class, 'index'])
    ->middleware(['auth', 'can_access:cost_estimates'])
    ->name('cost-estimates');

// CE export as CSV (opens in Excel) — Part 9.
Route::get('/bookings/{id}/ce-export', [BookingDetailController::class, 'ceExport'])
    ->middleware(['auth', 'can_access:bookings'])
    ->whereNumber('id')
    ->name('booking-detail.ce-export');

// Staff-side inbox for the homepage support chat (client_support_messages) — previously
// client-only with no staff reply path at all.
Route::get('/support-chat', [SupportChatController::class, 'index'])
    ->middleware(['auth', 'can_access:support_chat'])
    ->name('support-chat');

Route::match(['get', 'post'], '/support-chat/{clientId}', [SupportChatController::class, 'show'])
    ->middleware(['auth', 'can_access:support_chat'])
    ->whereNumber('clientId')
    ->name('support-chat.show');

Route::get('/support-chat/{clientId}/attachment/{messageId}', [SupportChatController::class, 'attachment'])
    ->middleware(['auth', 'can_access:support_chat'])
    ->whereNumber('clientId')->whereNumber('messageId')
    ->name('support-chat.attachment');

Route::get('/support-chat/{clientId}/poll', [SupportChatController::class, 'poll'])
    ->middleware(['auth', 'can_access:support_chat'])
    ->whereNumber('clientId')
    ->name('support-chat.poll');

Route::match(['get', 'post'], '/checklist', [ChecklistController::class, 'index'])
    ->middleware(['auth', 'can_access:bookings'])
    ->name('checklist');

// Printable check-out/check-in record.
Route::get('/checklist/print', [ChecklistController::class, 'print'])
    ->middleware(['auth', 'can_access:bookings'])
    ->name('checklist.print');

// No 'auth' middleware: this is a JSON API called via fetch() from the client-facing
// catalog on the home page. It returns a JSON error for guests/non-clients itself,
// matching the legacy contract, instead of redirecting to /login.
Route::match(['get', 'post'], '/cart', [CartController::class, 'handle'])->name('cart');

// Same no-'auth'-middleware contract as /cart above — JSON error for guests/non-clients.
Route::match(['get', 'post'], '/favorites', [FavoritesController::class, 'handle'])->name('favorites');

// No 'can_access' middleware: both staff (viewing any booking's CE) and clients (viewing
// only their own, enforced in CePreviewController::bookingMode()) legitimately use this
// route, so a single-module gate doesn't fit — 'auth' alone plus the controller-side
// ownership check is the intended contract here.
Route::get('/ce-preview', [CePreviewController::class, 'index'])->middleware(['auth'])->name('ce-preview');

// No 'auth' middleware, matching legacy: a public tile-fetching proxy with zero
// login/session check, used by the water-detection canvas helper on the location maps.
Route::get('/tile-proxy', [TileProxyController::class, 'show'])->name('tile-proxy');

Route::match(['get', 'post'], '/my-bookings/{id}', [ClientBookingDetailController::class, 'show'])
    ->middleware(['auth'])
    ->whereNumber('id')
    ->name('client-booking-detail');

Route::get('/my-bookings/{id}/attachment/{commentId}', [ClientBookingDetailController::class, 'attachment'])
    ->middleware(['auth'])
    ->whereNumber(['id', 'commentId'])
    ->name('client-booking-attachment');

// Uses a direct role check inside the controller (matching legacy's crew_portal.php,
// which requires hasRole(['crew'])) rather than can_access — the crew role has no
// entries in ROLE_PERMISSIONS/role_permissions since it's a separate self-service portal.
Route::match(['get', 'post'], '/crew-portal', [CrewPortalController::class, 'index'])
    ->middleware(['auth'])
    ->name('crew-portal');

Route::get('/crew-portal/checklist-print', [CrewPortalController::class, 'printChecklist'])
    ->middleware(['auth'])
    ->name('crew-portal.checklist-print');

Route::get('/crew-portal/attendance-print', [CrewPortalController::class, 'printAttendance'])
    ->middleware(['auth'])
    ->name('crew-portal.attendance-print');

Route::match(['get', 'post'], '/equipment', [EquipmentController::class, 'index'])
    ->middleware(['auth', 'can_access:equipment'])
    ->name('equipment');

Route::match(['get', 'post'], '/accessories', [AccessoriesController::class, 'index'])
    ->middleware(['auth', 'can_access:accessories'])
    ->name('accessories');

Route::match(['get', 'post'], '/crew', [CrewController::class, 'index'])
    ->middleware(['auth', 'can_access:crew'])
    ->name('crew');

Route::match(['get', 'post'], '/attendance', [AttendanceController::class, 'index'])
    ->middleware(['auth', 'can_access:attendance'])
    ->name('attendance');

// Printable attendance record for one booking — staff-side counterpart to
// crew-portal.attendance-print above.
Route::get('/attendance/print', [AttendanceController::class, 'print'])
    ->middleware(['auth', 'can_access:attendance'])
    ->name('attendance.print');

Route::match(['get', 'post'], '/incidents', [IncidentsController::class, 'index'])
    ->middleware(['auth', 'can_access:incidents'])
    ->name('incidents');

Route::get('/incident-print/{id}', [IncidentsController::class, 'print'])
    ->middleware(['auth', 'can_access:incidents'])
    ->whereNumber('id')
    ->name('incident-print');

// Uses a direct role check inside the controller (matching legacy's requireRole()) rather
// than can_access:clients — the legacy page allows the 'admin' role even though 'admin'
// has no entries in ROLE_PERMISSIONS/role_permissions at all.
Route::match(['get', 'post'], '/clients', [ClientsController::class, 'index'])
    ->middleware(['auth'])
    ->name('clients');

// A single client's documents (Part 18) — not a general client-detail page, this app has
// none; reached via a "Documents" button on the clients list.
Route::get('/clients/{id}/documents', [ClientDocumentsController::class, 'show'])
    ->middleware(['auth', 'can_access:clients'])
    ->whereNumber('id')
    ->name('client-documents');

// Documents (Part 18) — access-controlled, unlike ImageUpload's public-webroot pattern.
// store/download/destroy all re-check the specific parent booking/client inside the
// controller; a blanket route gate can't express "only if it's THEIR booking".
Route::post('/documents', [DocumentController::class, 'store'])
    ->middleware(['auth'])
    ->name('documents.store');
Route::get('/documents/{id}/download', [DocumentController::class, 'download'])
    ->middleware(['auth'])
    ->whereNumber('id')
    ->name('documents.download');
Route::delete('/documents/{id}', [DocumentController::class, 'destroy'])
    ->middleware(['auth'])
    ->whereNumber('id')
    ->name('documents.destroy');

// Same "controller self-checks access" contract as Documents above — staff via
// role_permissions.billing, client via booking ownership.
Route::get('/payments/{id}/receipt', [PaymentReceiptController::class, 'show'])
    ->middleware(['auth'])
    ->whereNumber('id')
    ->name('payment-receipt');

Route::match(['get', 'post'], '/billing', [BillingController::class, 'index'])
    ->middleware(['auth', 'can_access:billing'])
    ->name('billing');

// Was: requireLogin() only (no module gate) — any authenticated user, client accounts
// included, could view any receipt/SOA print by guessing the id. Only staff with billing
// access print from here (see billing.blade.php's printer icons); clients view their own
// receipts through the separately-guarded payment-receipt route instead.
Route::get('/billing-print', [BillingPrintController::class, 'show'])
    ->middleware(['auth', 'can_access:billing'])
    ->name('billing-print');

Route::match(['get', 'post'], '/profile', [ProfileController::class, 'index'])
    ->middleware(['auth', 'can_access:profile'])
    ->name('profile');

Route::get('/activity', [ActivityController::class, 'index'])
    ->middleware(['auth', 'can_access:activity'])
    ->name('activity');

Route::match(['get', 'post'], '/transport', [TransportController::class, 'index'])
    ->middleware(['auth', 'can_access:transport'])
    ->name('transport');

Route::match(['get', 'post'], '/pos', [PosController::class, 'index'])
    ->middleware(['auth', 'can_access:pos'])
    ->name('pos');

Route::match(['get', 'post'], '/superadmin', [SuperAdminController::class, 'index'])
    ->middleware(['auth', 'can_access:superadmin'])
    ->name('superadmin');

// Database backup download — separate route since it streams a file rather than
// redirecting back into index()'s $msg-array/re-render flow like every other action.
Route::post('/superadmin/backup', [SuperAdminController::class, 'backup'])
    ->middleware(['auth', 'can_access:superadmin'])
    ->name('superadmin.backup');

Route::match(['get', 'post'], '/data-retention', [DataRetentionController::class, 'index'])
    ->middleware(['auth', 'can_access:data_retention'])
    ->name('data-retention');

Route::get('/users', function () {
    return redirect()->route('superadmin');
})->middleware(['auth'])->name('users');

Route::get('/reports', [ReportsController::class, 'index'])
    ->middleware(['auth', 'can_access:reports'])
    ->name('reports');

Route::match(['get', 'post'], '/reminders', [ReminderController::class, 'index'])
    ->middleware(['auth', 'can_access:reminders'])
    ->name('reminders');

Route::match(['get', 'post'], '/faqs', [FaqController::class, 'index'])
    ->middleware(['auth', 'can_access:faqs'])
    ->name('faqs');

Route::match(['get', 'post'], '/field-requests', [FieldRequestsController::class, 'index'])
    ->middleware(['auth', 'can_access:field_requests'])
    ->name('field-requests');

Route::match(['get', 'post'], '/repair-purchase', [RepairPurchaseController::class, 'index'])
    ->middleware(['auth', 'can_access:repair_purchase'])
    ->name('repair-purchase');
