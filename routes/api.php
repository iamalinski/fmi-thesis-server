<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\ArticleController;
// use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\DashboardController;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/register-check-user-data', [AuthController::class, 'registerCheckUserData']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Profile
    Route::put('/profile/personal/{id}', [ProfileController::class, 'updatePersonalInfo']);
    Route::put('/profile/company', [ProfileController::class, 'updateCompanyInfo']);
    Route::put('/profile/password', [ProfileController::class, 'changePassword']);

    // Dashboard (one endpoint per statistic)
    Route::get('/dashboard/total-revenue', [DashboardController::class, 'totalRevenue']);
    Route::get('/dashboard/invoices-count', [DashboardController::class, 'invoicesCount']);
    Route::get('/dashboard/active-articles', [DashboardController::class, 'activeArticles']);
    Route::get('/dashboard/top-clients', [DashboardController::class, 'topClients']);
    Route::get('/dashboard/recent-invoices', [DashboardController::class, 'recentInvoices']);
    Route::get('/dashboard/top-products', [DashboardController::class, 'topProducts']);

    // Clients
    Route::get('/clients', [ClientController::class, 'index']);
    Route::post('/clients', [ClientController::class, 'store']);
    Route::get('/clients/{id}', [ClientController::class, 'show']);
    Route::put('/clients/{id}', [ClientController::class, 'update']);
    Route::delete('/clients/{id}', [ClientController::class, 'destroy']);

    // Articles
    Route::get('/articles', [ArticleController::class, 'index']);
    Route::post('/articles', [ArticleController::class, 'store']);
    Route::get('/articles/{id}', [ArticleController::class, 'show']);
    Route::put('/articles/{id}', [ArticleController::class, 'update']);
    Route::delete('/articles/{id}', [ArticleController::class, 'destroy']);

    // Sales
    // Route::get('/sales', [SaleController::class, 'index']);
    // Route::post('/sales', [SaleController::class, 'store']);
    // Route::get('/sales/{id}', [SaleController::class, 'show']);
    // Route::put('/sales/{id}', [SaleController::class, 'update']);
    // Route::delete('/sales/{id}', [SaleController::class, 'destroy']);

    // Invoices
    Route::get('/invoices', [InvoiceController::class, 'index']);
    Route::post('/invoices', [InvoiceController::class, 'store']);
    Route::get('/invoices/{id}/download', [InvoiceController::class, 'download']);
    Route::post('/invoices/{id}/send', [InvoiceController::class, 'send']);
    Route::patch('/invoices/{id}/status', [InvoiceController::class, 'updateStatus']);
    Route::patch('/invoices/{id}/recurrence', [InvoiceController::class, 'updateRecurrence']);
    Route::delete('/invoices/{id}/recurrence', [InvoiceController::class, 'destroyRecurrence']);
    Route::get('/invoices/{id}', [InvoiceController::class, 'show']);
    Route::put('/invoices/{id}', [InvoiceController::class, 'update']);
    Route::delete('/invoices/{id}', [InvoiceController::class, 'destroy']);
});
