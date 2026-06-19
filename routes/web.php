<?php

use App\Http\Controllers\AttendeeController;
use App\Http\Controllers\EventController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/events')->name('home');

Route::get('events', [EventController::class, 'index'])->name('events.index');
Route::get('events/data', [EventController::class, 'data'])->name('events.data');

// Visual 1 — card grid.
Route::get('events-visual-1', [EventController::class, 'visualOne'])->name('events.visual1');
Route::get('events-visual-1/data', [EventController::class, 'gridData'])->name('events.visual1.data');

// Visual 2 — interactive map.
Route::get('events-visual-2', [EventController::class, 'visualTwo'])->name('events.visual2');
Route::get('events-visual-2/map', [EventController::class, 'mapData'])->name('events.visual2.map');
Route::get('events-visual-2/data', [EventController::class, 'gridData'])->name('events.visual2.data');

Route::get('events/{event}', [EventController::class, 'show'])->name('events.show');
Route::post('events/{event}/attendees', [AttendeeController::class, 'store'])->name('events.attendees.store');

Route::inertia('dashboard', 'Dashboard')->name('dashboard');

require __DIR__.'/settings.php';
