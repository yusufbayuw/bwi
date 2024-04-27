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
    if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
        $_SERVER['REMOTE_ADDR'] = $_SERVER["HTTP_X_FORWARDED_FOR"];
    }
    $userIP = $_SERVER['REMOTE_ADDR']; //$request->ip();
    $userIParray = explode('.', $userIP);
    if ($userIParray[0] === "10") {
        return '<p>' . $userIP . '</p>'; //view('welcome');
    } elseif ($userIParray[0] === "172") {
        return '<p>' . $userIP . '</p>'; //view('welcome');
    } elseif ($userIParray[0] === "192") {
        if ($userIParray[1] === "168") {
            return '<p>' . $userIP . '</p>'; //view('welcome');
        } else {
            return '<p>' . $userIP . '</p>'; //redirect('/bwi');
        }
    } else {
        return '<p>' . $userIP . '</p>'; //redirect('/bwi');
    }
});
Route::get('/privacy-policy', function () {
    return view('privacy');
});
Route::get('/terms-and-conditions', function () {
    return view('tnc');
});
Route::get('pdf/{cabang_id}/{jenis}', [LaporanBulananCabangController::class, 'laporanBulananCabang']);
