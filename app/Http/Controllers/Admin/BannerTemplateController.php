<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BannerPosition;
use App\Enums\BannerTemplateAvailability;
use App\Enums\BannerTemplateCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBannerTemplateRequest;
use App\Http\Requests\Admin\UpdateBannerTemplateRequest;
use App\Models\BannerTemplate;
use App\Services\Banner\BannerTemplateImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BannerTemplateController extends Controller
{
    public function __construct(private readonly BannerTemplateImageService $images) {}

    public function index(Request $request): View
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'category' => $request->query('category', ''),
            'availability' => $request->query('availability', ''),
            'default_position' => $request->query('default_position', ''),
            'status' => $request->query('status', ''),
        ];

        $templates = BannerTemplate::query()
            ->withCount('banners')
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $query->where(function ($query) use ($filters): void {
                    $query->where('name', 'like', '%'.$filters['search'].'%')
                        ->orWhere('code', 'like', '%'.$filters['search'].'%')
                        ->orWhere('default_title', 'like', '%'.$filters['search'].'%');
                });
            })
            ->when(BannerTemplateCategory::tryFrom((string) $filters['category']) !== null, fn ($query) => $query->where('category', $filters['category']))
            ->when(BannerTemplateAvailability::tryFrom((string) $filters['availability']) !== null, fn ($query) => $query->where('availability', $filters['availability']))
            ->when(BannerPosition::tryFrom((string) $filters['default_position']) !== null, fn ($query) => $query->where('default_position', $filters['default_position']))
            ->when(in_array($filters['status'], [BannerTemplate::STATUS_ACTIVE, BannerTemplate::STATUS_INACTIVE], true), fn ($query) => $query->where('status', $filters['status']))
            ->ordered()
            ->paginate(20)
            ->withQueryString();

        return view('admin.banner-templates.index', [
            'templates' => $templates,
            'filters' => $filters,
            'categories' => BannerTemplateCategory::options(),
            'availabilities' => BannerTemplateAvailability::options(),
            'positions' => BannerPosition::options(),
            'statusOptions' => [BannerTemplate::STATUS_ACTIVE => 'Active', BannerTemplate::STATUS_INACTIVE => 'Inactive'],
        ]);
    }

    public function create(): View
    {
        return view('admin.banner-templates.create', $this->formData(new BannerTemplate([
            'status' => BannerTemplate::STATUS_ACTIVE,
            'availability' => BannerTemplateAvailability::BOTH,
            'category' => BannerTemplateCategory::GENERAL,
            'default_position' => BannerPosition::STORE_HERO->value,
            'sort_order' => 0,
        ])));
    }

    public function store(StoreBannerTemplateRequest $request): RedirectResponse
    {
        $uuid = (string) Str::uuid();
        $desktopPath = $this->images->store($request->file('desktop_image'), $uuid, 'desktop');
        $mobilePath = $request->hasFile('mobile_image')
            ? $this->images->store($request->file('mobile_image'), $uuid, 'mobile')
            : null;

        try {
            $template = DB::transaction(fn (): BannerTemplate => BannerTemplate::query()->create([
                ...$request->templateData(),
                'uuid' => $uuid,
                'desktop_image_path' => $desktopPath,
                'mobile_image_path' => $mobilePath,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]));
        } catch (\Throwable $throwable) {
            $this->images->delete($desktopPath);
            $this->images->delete($mobilePath);

            throw $throwable;
        }

        return redirect()->route('admin.banner-templates.edit', $template)->with('success', 'Banner template created successfully.');
    }

    public function edit(BannerTemplate $bannerTemplate): View
    {
        abort_if($bannerTemplate->trashed(), 404);

        return view('admin.banner-templates.edit', $this->formData($bannerTemplate));
    }

    public function update(UpdateBannerTemplateRequest $request, BannerTemplate $bannerTemplate): RedirectResponse
    {
        abort_if($bannerTemplate->trashed(), 404);

        $oldDesktop = $bannerTemplate->desktop_image_path;
        $oldMobile = $bannerTemplate->mobile_image_path;
        $newDesktop = null;
        $newMobile = null;

        $data = [
            ...$request->templateData(),
            'updated_by' => Auth::id(),
        ];

        if ($request->hasFile('desktop_image')) {
            $newDesktop = $this->images->store($request->file('desktop_image'), $bannerTemplate, 'desktop');
            $data['desktop_image_path'] = $newDesktop;
        }

        if ($request->hasFile('mobile_image')) {
            $newMobile = $this->images->store($request->file('mobile_image'), $bannerTemplate, 'mobile');
            $data['mobile_image_path'] = $newMobile;
        } elseif ($request->boolean('remove_mobile_image')) {
            $data['mobile_image_path'] = null;
        }

        try {
            DB::transaction(function () use ($bannerTemplate, $data): void {
                $bannerTemplate->forceFill($data)->save();
            });
        } catch (\Throwable $throwable) {
            $this->images->delete($newDesktop);
            $this->images->delete($newMobile);

            throw $throwable;
        }

        if (($data['desktop_image_path'] ?? $oldDesktop) !== $oldDesktop) {
            $this->images->delete($oldDesktop);
        }

        if (array_key_exists('mobile_image_path', $data) && $data['mobile_image_path'] !== $oldMobile) {
            $this->images->delete($oldMobile);
        }

        return redirect()->route('admin.banner-templates.edit', $bannerTemplate)->with('success', 'Banner template updated successfully.');
    }

    public function toggleStatus(BannerTemplate $bannerTemplate): RedirectResponse
    {
        abort_if($bannerTemplate->trashed(), 404);

        $bannerTemplate->forceFill([
            'status' => $bannerTemplate->status === BannerTemplate::STATUS_ACTIVE
                ? BannerTemplate::STATUS_INACTIVE
                : BannerTemplate::STATUS_ACTIVE,
            'updated_by' => Auth::id(),
        ])->save();

        return redirect()->route('admin.banner-templates.index')->with('success', 'Banner template status updated successfully.');
    }

    private function formData(BannerTemplate $template): array
    {
        return [
            'template' => $template,
            'categories' => BannerTemplateCategory::options(),
            'availabilities' => BannerTemplateAvailability::options(),
            'positions' => BannerPosition::options(),
            'positionMeta' => collect(BannerPosition::cases())->mapWithKeys(fn (BannerPosition $position): array => [$position->value => [
                'scope' => $position->scope(),
                'label' => $position->label(),
                'description' => $position->description(),
                'dimensions' => $position->recommendedDimensions(),
            ]])->all(),
            'statusOptions' => [BannerTemplate::STATUS_ACTIVE => 'Active', BannerTemplate::STATUS_INACTIVE => 'Inactive'],
        ];
    }
}
