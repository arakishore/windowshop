<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductReview;
use App\Models\ProductReviewImage;
use App\Services\Review\ProductReviewImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductReviewController extends Controller
{
    public function __construct(private readonly ProductReviewImageService $images) {}

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');
        $isTrash = $status === 'trash';
        $reviews = ($isTrash ? ProductReview::onlyTrashed() : ProductReview::query())
            ->with(['product:id,product_name,uuid', 'customer:id,name,email', 'order:id,order_number', 'images'])
            ->when(in_array($status, $this->statuses(), true), fn ($query) => $query->where('status', $status))
            ->latest()->paginate(20)->withQueryString();

        return view('admin.product-reviews.index', ['reviews' => $reviews, 'status' => $status, 'statuses' => [...$this->statuses(), 'trash'], 'isTrash' => $isTrash]);
    }

    public function approve(ProductReview $review): RedirectResponse
    {
        $this->moderate($review, ProductReview::STATUS_APPROVED);

        return back()->with('success', 'Review approved.');
    }

    public function bulkAction(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject', 'delete', 'restore', 'force_delete'])],
            'review_ids' => ['required', 'array', 'min:1'],
            'review_ids.*' => ['integer', 'distinct', Rule::exists('product_reviews', 'id')],
        ]);
        $count = match ($data['action']) {
            'approve' => $this->bulkModerate($data['review_ids'], ProductReview::STATUS_APPROVED),
            'reject' => $this->bulkModerate($data['review_ids'], ProductReview::STATUS_REJECTED),
            'delete' => ProductReview::query()->whereKey($data['review_ids'])->delete(),
            'restore' => ProductReview::onlyTrashed()->whereKey($data['review_ids'])->restore(),
            'force_delete' => $this->bulkForceDelete($data['review_ids']),
        };

        return back()->with('success', "{$count} review(s) updated successfully.");
    }

    public function reject(ProductReview $review): RedirectResponse
    {
        $this->moderate($review, ProductReview::STATUS_REJECTED);

        return back()->with('success', 'Review rejected.');
    }

    public function destroy(ProductReview $review): RedirectResponse
    {
        $review->delete();

        return back()->with('success', 'Review moved to Trash.');
    }

    public function restore(ProductReview $review): RedirectResponse
    {
        abort_unless($review->trashed(), 404);
        $review->restore();

        return back()->with('success', 'Review restored.');
    }

    public function forceDelete(ProductReview $review): RedirectResponse
    {
        abort_unless($review->trashed(), 404);
        $this->forceDeleteReviews(collect([$review]));

        return back()->with('success', 'Review permanently deleted.');
    }

    private function moderate(ProductReview $review, string $status): void
    {
        $review->update(['status' => $status, 'moderated_by' => Auth::id(), 'moderated_at' => now()]);
    }

    private function bulkModerate(array $ids, string $status): int
    {
        return DB::transaction(fn (): int => ProductReview::query()->whereKey($ids)->update([
            'status' => $status,
            'moderated_by' => Auth::id(),
            'moderated_at' => now(),
            'updated_at' => now(),
        ]));
    }

    private function bulkForceDelete(array $ids): int
    {
        $reviews = ProductReview::onlyTrashed()->with('images')->whereKey($ids)->get();
        $this->forceDeleteReviews($reviews);

        return $reviews->count();
    }

    private function forceDeleteReviews($reviews): void
    {
        $images = $reviews->flatMap->images;
        $reviewsById = $reviews->keyBy('id');
        $images->each(fn (ProductReviewImage $image) => $image->setRelation('review', $reviewsById->get($image->product_review_id)));
        DB::transaction(fn () => $reviews->each->forceDelete());
        $images->each(fn (ProductReviewImage $image) => $this->images->deleteFiles($image));
    }

    private function statuses(): array
    {
        return [ProductReview::STATUS_PENDING, ProductReview::STATUS_APPROVED, ProductReview::STATUS_REJECTED];
    }
}
