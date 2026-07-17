<?php

use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ChatController::class, 'index'])->name('chat');
Route::post('/generate', [ChatController::class, 'generate'])->name('chat.generate');
