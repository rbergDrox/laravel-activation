<?php

use Illuminate\Support\Facades\Route;
use Shapperize\Activation\Http\Controllers\ActivationController;

Route::post('{subject}/tour', [ActivationController::class, 'recordTour'])->name('tour');
Route::post('{subject}/hints/dismiss', [ActivationController::class, 'dismissHint'])->name('hints.dismiss');
Route::post('{subject}/celebrate', [ActivationController::class, 'celebrate'])->name('celebrate');
