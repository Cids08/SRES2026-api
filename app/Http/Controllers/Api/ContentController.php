<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Content;
use Illuminate\Http\Request;

class ContentController extends Controller
{
    public function index()
    {
        return response()->json(Content::published()->get());
    }

    public function show($slug)
    {
        $content = Content::where('slug', $slug)
                          ->published()
                          ->firstOrFail();

        return response()->json($content);
    }
}