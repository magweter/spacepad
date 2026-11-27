<?php

namespace App\Http\Controllers;

use App\Support\HelpArticles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Data for the portal's Help & Roadmap panel, loaded lazily when the panel opens.
 */
class HelpController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Support and the roadmap run on spacepad.io only, like support.ask and roadmap.vote.
        abort_if(config('settings.is_self_hosted'), 404);

        $user = $request->user();

        return response()->json([
            'faqs' => collect(HelpArticles::faqs())
                ->map(fn (array $faq) => [
                    'id' => $faq['id'],
                    'category' => $faq['category'],
                    'categoryLabel' => $faq['categoryLabel'],
                    'question' => $faq['question'],
                    'answer' => $faq['answer'],
                ])
                ->values(),
            'categories' => collect(HelpArticles::categories())
                ->map(fn (array $category) => ['id' => $category['id'], 'label' => $category['label']])
                ->values(),
            'roadmap' => collect(HelpArticles::roadmapItems($user))
                ->map(fn (array $item) => collect($item)->except(['statusClass', 'isPulse'])->all())
                ->values(),
            'email' => $user->email,
        ]);
    }
}
