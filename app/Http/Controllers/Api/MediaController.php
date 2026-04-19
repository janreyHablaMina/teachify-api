<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    public function showPublic(string $path)
    {
        $normalized = ltrim($path, '/');
        if ($normalized === '' || str_contains($normalized, '..')) {
            abort(404);
        }

        if (! Storage::disk('public')->exists($normalized)) {
            abort(404);
        }

        return Storage::disk('public')->response($normalized);
    }
}

