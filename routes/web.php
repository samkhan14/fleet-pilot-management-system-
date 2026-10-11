<?php

use App\Http\Controllers\BillingController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DispatchController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DriveController;
use App\Http\Controllers\ExceptionController;
use App\Http\Controllers\FleetController;
use App\Http\Controllers\PublicTrackingController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Settings\OrganizationController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\TripController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::get('/track/{token}', [PublicTrackingController::class, 'show'])
    ->middleware('throttle:30,1')
    ->name('track.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('shipments', [ShipmentController::class, 'index'])->name('shipments.index');
    Route::get('shipments/create', [ShipmentController::class, 'create'])->name('shipments.create');
    Route::post('shipments', [ShipmentController::class, 'store'])->name('shipments.store');
    Route::get('shipments/{shipment}', [ShipmentController::class, 'show'])->name('shipments.show');
    Route::post('shipments/{shipment}/transition', [ShipmentController::class, 'transition'])->name('shipments.transition');
    Route::post('shipments/{shipment}/reschedule', [ShipmentController::class, 'reschedule'])->name('shipments.reschedule');
    Route::post('shipments/{shipment}/assign', [ShipmentController::class, 'assign'])->name('shipments.assign');
    Route::post('shipments/{shipment}/unassign', [ShipmentController::class, 'unassign'])->name('shipments.unassign');
    Route::post('shipments/{shipment}/tracking-token', [ShipmentController::class, 'token'])->name('shipments.token');
    Route::post('shipments/{shipment}/exceptions', [ShipmentController::class, 'reportException'])->name('shipments.exceptions.store');

    Route::get('dispatch', [DispatchController::class, 'index'])->name('dispatch.index');
    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    Route::post('customers/{customer}/contacts', [CustomerController::class, 'storeContact'])->name('customers.contacts.store');
    Route::post('customers/{customer}/addresses', [CustomerController::class, 'storeAddress'])->name('customers.addresses.store');
    Route::post('customers/{customer}/orders', [CustomerController::class, 'storeOrder'])->name('customers.orders.store');

    Route::get('drivers', [FleetController::class, 'drivers'])->name('drivers.index');
    Route::post('drivers', [FleetController::class, 'storeDriver'])->name('drivers.store');
    Route::patch('drivers/{driver}', [FleetController::class, 'updateDriver'])->name('drivers.update');
    Route::get('vehicles', [FleetController::class, 'vehicles'])->name('vehicles.index');
    Route::post('vehicles', [FleetController::class, 'storeVehicle'])->name('vehicles.store');
    Route::patch('vehicles/{vehicle}', [FleetController::class, 'updateVehicle'])->name('vehicles.update');

    Route::get('trips', [TripController::class, 'index'])->name('trips.index');
    Route::post('trips', [TripController::class, 'store'])->name('trips.store');
    Route::get('trips/{trip}', [TripController::class, 'show'])->name('trips.show');
    Route::post('trips/{trip}/reorder', [TripController::class, 'reorder'])->name('trips.reorder');

    Route::get('drive', [DriveController::class, 'index'])->name('drive.index');
    Route::post('drive/shipments/{shipment}', [DriveController::class, 'act'])->name('drive.act');

    Route::get('exceptions', [ExceptionController::class, 'index'])->name('exceptions.index');
    Route::post('exceptions/{exception}/resolve', [ExceptionController::class, 'resolve'])->name('exceptions.resolve');

    Route::get('billing', [BillingController::class, 'index'])->name('billing.index');
    Route::post('billing/charges', [BillingController::class, 'store'])->name('billing.store');
    Route::patch('billing/charges/{charge}', [BillingController::class, 'update'])->name('billing.update');
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');

    Route::post('documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('files/{file}', [DocumentController::class, 'download'])->name('files.download');

    Route::get('settings/organization', [OrganizationController::class, 'edit'])->name('organization.edit');
    Route::patch('settings/organization', [OrganizationController::class, 'update'])->name('organization.update');
    Route::post('settings/members', [OrganizationController::class, 'storeMember'])->name('organization.members.store');
    Route::put('settings/permissions', [OrganizationController::class, 'updateGrants'])->name('organization.permissions.update');
});

require __DIR__.'/settings.php';
