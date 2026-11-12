<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadFileRequest;
use App\Support\Uploads;
use Illuminate\Http\JsonResponse;

class UploadController extends Controller
{
    public function store(UploadFileRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $folder = (string) ($request->input('folder') ?: 'misc');
        $url = Uploads::store($file, $folder);

        return response()->json([
            'data' => [
                'url' => $url,
                'name' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
            ],
        ], 201);
    }
}
