<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFaqRequest;
use App\Http\Requests\Admin\UpdateFaqRequest;
use App\Models\Faq;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FaqController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'category' => $request->query('category'),
            'status' => $request->query('status'),
        ];

        $faqs = Faq::query()
            ->when($filters['search'] !== '', fn ($query) => $query->where(function ($query) use ($filters): void {
                $query->where('question', 'like', "%{$filters['search']}%")
                    ->orWhere('answer', 'like', "%{$filters['search']}%");
            }))
            ->when(in_array($filters['category'], array_keys(Faq::categories()), true), fn ($query) => $query->where('category', $filters['category']))
            ->when(in_array($filters['status'], [Faq::STATUS_ACTIVE, Faq::STATUS_INACTIVE], true), fn ($query) => $query->where('status', $filters['status']))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate((int) config('admin.pagination.per_page', 15))
            ->withQueryString();

        return view('admin.faqs.index', [
            'faqs' => $faqs,
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('admin.faqs.create', [
            'faq' => null,
        ]);
    }

    public function store(StoreFaqRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $actorId = Auth::id();

        Faq::create([
            'category' => $data['category'],
            'question' => trim($data['question']),
            'answer' => trim($data['answer']),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'status' => $data['status'],
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);

        return redirect()
            ->route('admin.faqs.index')
            ->with('success', 'FAQ created successfully.');
    }

    public function edit(Faq $faq): View
    {
        return view('admin.faqs.edit', [
            'faq' => $faq,
        ]);
    }

    public function update(UpdateFaqRequest $request, Faq $faq): RedirectResponse
    {
        $data = $request->validated();

        $faq->forceFill([
            'category' => $data['category'],
            'question' => trim($data['question']),
            'answer' => trim($data['answer']),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'status' => $data['status'],
            'updated_by' => Auth::id(),
        ])->save();

        return redirect()
            ->route('admin.faqs.edit', $faq)
            ->with('success', 'FAQ updated successfully.');
    }

    public function destroy(Faq $faq): RedirectResponse
    {
        DB::transaction(function () use ($faq): void {
            $faq->forceFill([
                'deleted_by' => Auth::id(),
            ])->save();

            $faq->delete();
        });

        return redirect()
            ->route('admin.faqs.index')
            ->with('success', 'FAQ deleted successfully.');
    }
}
