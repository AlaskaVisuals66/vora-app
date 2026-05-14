<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => redirect('/conversations'))->name('home');
Route::get('/login', fn () => redirect('/conversations'))->name('login');
Route::get('/dashboard', fn () => Inertia::render('Dashboard/Index'))->name('dashboard');
Route::get('/conversations/sector/{slug}/{ticket?}', fn ($slug, $ticket = null) => Inertia::render('Conversations/Index', ['ticketId' => $ticket, 'sectorSlug' => $slug]))->name('conversations.sector');
Route::get('/conversations/{ticket?}', fn ($ticket = null) => Inertia::render('Conversations/Index', ['ticketId' => $ticket]))->where('ticket', '[0-9]+')->name('conversations');
Route::get('/sectors', fn () => Inertia::render('Sectors/Index'))->name('sectors.index');
Route::get('/users', fn () => Inertia::render('Users/Index'))->name('users.index');
Route::get('/settings', fn () => Inertia::render('Settings/Index'))->name('settings');
Route::get('/profile', fn () => Inertia::render('Profile/Index'))->name('profile');

if (app()->environment('local')) {
    Route::get('/_ui', fn () => Inertia::render('_Ui/Index'))->name('_ui');
}

