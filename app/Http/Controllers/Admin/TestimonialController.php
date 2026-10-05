<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTestimonialRequest;
use App\Http\Requests\Admin\UpdateTestimonialRequest;
use App\Models\Testimonial;
use App\Services\Image\ImageVariantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class TestimonialController extends Controller
{
    public function __construct(
        private readonly ImageVariantService $imageVariantService,
    ) {}

    public function index(Request $request): View
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'type' => $request->query('type'),
            'status' => $request->query('status'),
        ];

        $testimonials = Testimonial::query()
            ->when($filters['search'] !== '', fn ($query) => $query->where(function ($query) use ($filters): void {
                $query->where('name', 'like', "%{$filters['search']}%")
                    ->orWhere('business_name', 'like', "%{$filters['search']}%")
                    ->orWhere('location', 'like', "%{$filters['search']}%");
            }))
            ->when(in_array($filters['type'], [Testimonial::TYPE_CUSTOMER, Testimonial::TYPE_MERCHANT], true), fn ($query) => $query->where('type', $filters['type']))
            ->when(in_array($filters['status'], [Testimonial::STATUS_ACTIVE, Testimonial::STATUS_INACTIVE], true), fn ($query) => $query->where('status', $filters['status']))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate((int) config('admin.pagination.per_page', 15))
            ->withQueryString();

        return view('admin.testimonials.index', [
            'testimonials' => $testimonials,
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('admin.testimonials.create', [
            'testimonial' => null,
        ]);
    }

    public function store(StoreTestimonialRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $actorId = Auth::id();

        $testimonial = Testimonial::create([
            'type' => $data['type'],
            'name' => trim($data['name']),
            'photo_path' => null,
            'body' => trim($data['body']),
            'location' => $this->nullable($data['location'] ?? null),
            'rating' => $data['type'] === Testimonial::TYPE_CUSTOMER ? $this->rating($data['rating'] ?? null) : null,
            'business_name' => $data['type'] === Testimonial::TYPE_MERCHANT ? $this->nullable($data['business_name'] ?? null) : null,
            'designation' => $data['type'] === Testimonial::TYPE_MERCHANT ? $this->nullable($data['designation'] ?? null) : null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'status' => $data['status'],
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);

        if ($request->hasFile('photo')) {
            $testimonial->forceFill([
                'photo_path' => $this->replacePhoto($request, $testimonial, null),
            ])->save();
        }

        return redirect()
            ->route('admin.testimonials.index')
            ->with('success', 'Testimonial created successfully.');
    }

    public function edit(Testimonial $testimonial): View
    {
        return view('admin.testimonials.edit', [
            'testimonial' => $testimonial,
        ]);
    }

    public function update(UpdateTestimonialRequest $request, Testimonial $testimonial): RedirectResponse
    {
        $data = $request->validated();
        $photoPath = $testimonial->photo_path;

        if ($request->boolean('remove_photo')) {
            $this->deletePhotoDirectory($photoPath);
            $photoPath = null;
        }

        if ($request->hasFile('photo')) {
            $photoPath = $this->replacePhoto($request, $testimonial, $photoPath);
        }

        $testimonial->forceFill([
            'type' => $data['type'],
            'name' => trim($data['name']),
            'photo_path' => $photoPath,
            'body' => trim($data['body']),
            'location' => $this->nullable($data['location'] ?? null),
            'rating' => $data['type'] === Testimonial::TYPE_CUSTOMER ? $this->rating($data['rating'] ?? null) : null,
            'business_name' => $data['type'] === Testimonial::TYPE_MERCHANT ? $this->nullable($data['business_name'] ?? null) : null,
            'designation' => $data['type'] === Testimonial::TYPE_MERCHANT ? $this->nullable($data['designation'] ?? null) : null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'status' => $data['status'],
            'updated_by' => Auth::id(),
        ])->save();

        return redirect()
            ->route('admin.testimonials.edit', $testimonial)
            ->with('success', 'Testimonial updated successfully.');
    }

    public function destroy(Testimonial $testimonial): RedirectResponse
    {
        DB::transaction(function () use ($testimonial): void {
            $testimonial->forceFill([
                'deleted_by' => Auth::id(),
            ])->save();

            $testimonial->delete();
        });

        return redirect()
            ->route('admin.testimonials.index')
            ->with('success', 'Testimonial deleted successfully.');
    }

    private function replacePhoto(Request $request, Testimonial $testimonial, ?string $oldPath): string
    {
        $finalDirectory = "testimonials/{$testimonial->uuid}/photo";
        $pendingDirectory = "testimonials/{$testimonial->uuid}/photo-pending-".Str::uuid();

        try {
            $paths = $this->imageVariantService->store($request->file('photo'), 'testimonial', $pendingDirectory);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                'photo' => $exception->getMessage(),
            ]);
        }

        if ($oldPath) {
            $this->deletePhotoDirectory($oldPath);
        } else {
            Storage::disk('public')->deleteDirectory($finalDirectory);
        }

        Storage::disk('public')->makeDirectory($finalDirectory);

        foreach (Storage::disk('public')->files($pendingDirectory) as $file) {
            Storage::disk('public')->move($file, $finalDirectory.'/'.basename($file));
        }

        Storage::disk('public')->deleteDirectory($pendingDirectory);

        $webFile = basename($paths['web'] ?? array_values($paths)[0]);

        return "{$finalDirectory}/{$webFile}";
    }

    private function deletePhotoDirectory(?string $path): void
    {
        if (! $path) {
            return;
        }

        Storage::disk('public')->deleteDirectory(dirname($path));
    }

    private function nullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function rating(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
