<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\SaleController;
use App\Http\Controllers\API\ReportController;

// Ping / Test
Route::get('/ping', fn() => response()->json(['ok' => true]));
Route::get('/test', fn() => response()->json(['message' => 'API Usuarios/Ventas OK']));

// LOGIN
Route::post('/login', [AuthController::class, 'login']);

// USERS CRUD
Route::apiResource('users', UserController::class);

// SALES CRUD básico (adaptado a lo que tienes en el controlador)
Route::get('sales',            [SaleController::class, 'index']);
Route::get('sales/{id}',       [SaleController::class, 'show']);
Route::post('sales',           [SaleController::class, 'store']);
Route::post('sales/{id}/cancel', [SaleController::class, 'cancel']);

// REPORTES
Route::get('reports/sales', [ReportController::class, 'sales']);
