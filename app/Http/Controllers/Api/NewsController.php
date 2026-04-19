<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\News;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    /**
     * GET /api/news
     * Optional: ?category=<slug>  e.g. ?category=event
     */
    public function index(Request $request): JsonResponse
    {
        $query = News::published()
            ->with(['category', 'eventDetails'])
            ->latest('posted_at');

        if ($request->filled('category')) {
            $query->byCategory($request->category);
        }

        return response()->json(
            $query->get()->map(fn($n) => $this->format($n))
        );
    }

    /**
     * GET /api/news/{id}
     */
    public function show(News $news): JsonResponse
    {
        if (! $news->is_published) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $news->increment('view_count');

        return response()->json(
            $this->format($news->load(['category', 'eventDetails']))
        );
    }

    // No type hint on $news — avoids stdClass warning from static analysis
    private function format($news): array
    {
        $details = $news->eventDetails ?? collect();

        return [
            'id'             => $news->id,
            'category'       => [
                'id'    => $news->category?->id,
                'name'  => $news->category?->name,
                'slug'  => $news->category?->slug,
                'color' => $news->category?->color_class,
            ],
            'title'          => $news->title,
            'content'        => $news->content,
            'date'           => $news->posted_at?->format('F j, Y'),
            'is_featured'    => $news->is_featured,
            'event_date'     => $news->event_date?->format('F j, Y'),
            'event_location' => $news->event_location,
            'image_url'      => $news->image
                                    ? asset('storage/' . $news->image)
                                    : null,
            'view_count'     => $news->view_count,
            'details'        => [
                'items'      => $details->where('detail_type', 'item')
                                    ->map(fn($d) => [
                                        'label' => $d->detail_key,
                                        'value' => $d->detail_value,
                                    ])->values(),
                'highlights' => $details->where('detail_type', 'highlight')
                                    ->pluck('detail_value')
                                    ->values(),
                'body'       => $details->firstWhere('detail_type', 'body')?->detail_value,
            ],
        ];
    }
}