<?php

use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\ListingController;
use Illuminate\Support\Facades\Route;

Route::post('/labs/token', [\App\Http\Controllers\Auth\LabsController::class, 'token'])
    ->middleware('throttle:30,1')->name('labs.token');

Route::get('/items', [ItemController::class, 'index'])->name('items.index');
Route::get('/items/{item}', [ItemController::class, 'show'])->name('items.show');

Route::get('/listings', [ListingController::class, 'index'])->name('listings.index');
Route::get('/listings/{listing}', [ListingController::class, 'show'])->name('listings.show');
