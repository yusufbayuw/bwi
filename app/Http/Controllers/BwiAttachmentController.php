<?php

namespace App\Http\Controllers;

use App\Services\BwiAttachment;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class BwiAttachmentController extends Controller
{
    public function __invoke(Request $request, string $filename): BinaryFileResponse
    {
        abort_unless(BwiAttachment::validName($filename), 404);
        abort_unless(BwiAttachment::readableBy($request->user(), $filename), 404);
        return BwiAttachment::response($filename);
    }
}
