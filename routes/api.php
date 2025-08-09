<?php


use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\UserController;

Route::get('/test', fn () => response()->json(['message' => 'API ok']));
Route::apiResource('users', UserController::class);
Route::get('/ping', fn() => response()->json(['ok'=>true]));