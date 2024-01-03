<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class LaporanBulananCabangController extends Controller
{
    public function generatePDF()
    {
        $pdf = Pdf::setOptions(['dpi' => 150, 'isHtml5ParserEnabled' => true, 'defaultFont' => 'sans-serif'])->loadView('laporan.bulanan.cabang');
        return $pdf->stream();//download('invoice.pdf');
    }
}

