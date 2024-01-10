<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LaporanBulananCabangController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function (Request $request) {
    $userIP = $request->ip();
    $userIParray = explode('.', $userIP);
    if ($userIParray[0] === "10") {
        return view('welcome');
    } elseif ($userIParray[0] === "172") {
        return view('welcome');
    } elseif ($userIParray[0] === "192") {
        if ($userIParray[0] === "168") {
            return view('welcome');
        } else {
            return redirect('/bwi');
        }
    } else {
        return redirect('/bwi');
    }
});
Route::get('/privacy-policy', function () {
    return view('privacy');
});
Route::get('/terms-and-conditions', function () {
    return view('tnc');
});
Route::get('pdf', [LaporanBulananCabangController::class, 'generatePDF']);
