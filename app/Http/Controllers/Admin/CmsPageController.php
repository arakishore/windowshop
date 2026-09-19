<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CmsPageRequest;
use App\Models\CmsPage;
use App\Services\Merchant\ShopPageContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CmsPageController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'type' => (string) $request->query('type', ''),
            'status' => (string) $request->query('status', ''),
        ];

        $pages = CmsPage::query()
            ->where(function ($query): void {
                $query->where('page_type', CmsPage::TYPE_CUSTOM)
                    ->orWhereIn('page_key', array_keys(config('cms_pages.standard', [])));
            })
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($query) use ($filters): void {
                    $query->where('title', 'like', '%'.$filters['q'].'%')
                        ->orWhere('slug', 'like', '%'.$filters['q'].'%');
                });
            })
            ->when(in_array($filters['type'], [CmsPage::TYPE_STANDARD, CmsPage::TYPE_CUSTOM], true),
                fn ($query) => $query->where('page_type', $filters['type']))
            ->when(in_array($filters['status'], [CmsPage::STATUS_DRAFT, CmsPage::STATUS_PUBLISHED], true),
                fn ($query) => $query->where('status', $filters['status']))
            ->orderByRaw("CASE WHEN page_type = 'standard' THEN 0 ELSE 1 END")
            ->orderBy('title')
            ->paginate((int) config('admin.pagination.per_page', 15))
            ->withQueryString();

        return view('admin.cms-pages.index', compact('pages', 'filters'));
    }

    public function bulkAction(Request $request, ShopPageContent $content): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['publish', 'draft', 'delete'])],
            'page_ids' => ['required', 'array', 'min:1'],
            'page_ids.*' => ['required', 'integer', 'distinct', Rule::exists('cms_pages', 'id')],
        ]);

        $pages = CmsPage::query()->whereIn('id', $data['page_ids'])->get();
        if ($pages->contains(fn (CmsPage $page): bool => ! $this->isManageable($page))) {
            throw ValidationException::withMessages(['page_ids' => 'One or more selected pages are not managed by the marketplace CMS.']);
        }
        if ($data['action'] === 'delete' && $pages->contains('page_type', CmsPage::TYPE_STANDARD)) {
            throw ValidationException::withMessages(['page_ids' => 'Standard pages cannot be deleted.']);
        }
        if ($data['action'] === 'publish' && $pages->contains(
            fn (CmsPage $page) => trim(strip_tags($content->render((string) $page->body))) === ''
        )) {
            throw ValidationException::withMessages(['page_ids' => 'Add content to every selected page before publishing.']);
        }

        foreach ($pages as $page) {
            if ($data['action'] === 'delete') {
                $page->delete();
                continue;
            }

            $published = $data['action'] === 'publish';
            $page->forceFill([
                'status' => $published ? CmsPage::STATUS_PUBLISHED : CmsPage::STATUS_DRAFT,
                'published_at' => $published ? ($page->published_at ?? now()) : null,
            ])->save();
        }

        return redirect()->route('admin.cms-pages.index')->with('success', $pages->count().' marketplace page(s) updated.');
    }

    public function create(): View
    {
        return view('admin.cms-pages.form', [
            'page' => new CmsPage(['page_type' => CmsPage::TYPE_CUSTOM, 'status' => CmsPage::STATUS_DRAFT]),
        ]);
    }

    public function store(CmsPageRequest $request): RedirectResponse
    {
        $data = $request->pageData();
        $page = CmsPage::query()->create([
            ...$data,
            'page_type' => CmsPage::TYPE_CUSTOM,
            'page_key' => null,
            'published_at' => $data['status'] === CmsPage::STATUS_PUBLISHED ? now() : null,
        ]);

        return redirect()->route('admin.cms-pages.edit', $page)->with('success', 'Marketplace page created.');
    }

    public function edit(CmsPage $cmsPage): View
    {
        $this->ensureManageable($cmsPage);

        return view('admin.cms-pages.form', ['page' => $cmsPage]);
    }

    public function update(CmsPageRequest $request, CmsPage $cmsPage): RedirectResponse
    {
        $this->ensureManageable($cmsPage);

        $data = $request->pageData();
        $attributes = [
            'body' => $data['body'],
            'status' => $data['status'],
            'published_at' => $data['status'] === CmsPage::STATUS_PUBLISHED
                ? ($cmsPage->published_at ?? now())
                : null,
        ];

        if ($cmsPage->page_type === CmsPage::TYPE_CUSTOM) {
            $attributes['title'] = $data['title'];
            $attributes['slug'] = $data['slug'];
        }

        $cmsPage->forceFill($attributes)->save();

        return redirect()->route('admin.cms-pages.edit', $cmsPage)->with('success', 'Marketplace page saved.');
    }

    public function preview(CmsPage $cmsPage): View
    {
        $this->ensureManageable($cmsPage);

        return view('admin.cms-pages.preview', ['page' => $cmsPage]);
    }

    public function destroy(CmsPage $cmsPage): RedirectResponse
    {
        $this->ensureManageable($cmsPage);
        abort_unless($cmsPage->page_type === CmsPage::TYPE_CUSTOM, 403);
        $cmsPage->delete();

        return redirect()->route('admin.cms-pages.index')->with('success', 'Custom marketplace page deleted.');
    }

    private function ensureManageable(CmsPage $page): void
    {
        abort_unless($this->isManageable($page), 404);
    }

    private function isManageable(CmsPage $page): bool
    {
        return $page->page_type === CmsPage::TYPE_CUSTOM
            || array_key_exists((string) $page->page_key, config('cms_pages.standard', []));
    }
}
