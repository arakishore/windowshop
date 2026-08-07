<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BannerPosition;
use App\Enums\BannerTemplateAvailability;
use App\Enums\BannerTemplateCategory;
use App\Http\Controllers\Controller;
use App\Services\Banner\BannerTemplateLibraryService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BannerLibraryController extends Controller
{
    public function __construct(private readonly BannerTemplateLibraryService $library) {}

    public function index(Request $request): View
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'category' => $request->query('category', ''),
            'availability' => $request->query('availability', ''),
            'position' => $request->query('position', ''),
        ];

        $templates = $this->library->queryForAdmin($filters)
            ->when(BannerTemplateAvailability::tryFrom((string) $filters['availability']) !== null, fn ($query) => $query->where('availability', $filters['availability']))
            ->paginate(20)
            ->withQueryString();

        return view('admin.banner-library.index', [
            'templates' => $templates,
            'filters' => $filters,
            'categories' => BannerTemplateCategory::options(),
            'availabilities' => BannerTemplateAvailability::options(),
            'positions' => BannerPosition::options(),
        ]);
    }
}
