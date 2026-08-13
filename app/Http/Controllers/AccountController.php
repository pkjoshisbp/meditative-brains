<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\AffiliateProfile;
use App\Models\Order;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\TtsAudioProduct;
use App\Services\AffiliateService;
use App\Services\StudentPricingService;
use Illuminate\Support\Facades\Storage;

class AccountController extends Controller
{
    public function __construct(private StudentPricingService $studentPricing, private AffiliateService $affiliateService)
    {
        $this->middleware('auth');
    }

    public function dashboard()
    {
        $user = Auth::user();
        $orderCount   = $user->orders()->count();
        $activeSubscription = $user->subscriptions()->where('status', 'active')->latest()->first();
        $recentOrders = $user->orders()->latest()->limit(3)->get();

        return view('account.dashboard', compact('user', 'orderCount', 'activeSubscription', 'recentOrders'));
    }

    public function library()
    {
        $user = Auth::user();
        $activeSubscription = $user->subscriptions()->active()->latest()->first();
        $activePlan = $activeSubscription
            ? SubscriptionPlan::where('slug', $activeSubscription->plan_type)->first()
            : null;

        $purchasedProducts = $user->getPurchasedProducts()
            ->load(['category', 'media', 'linkedAudiobook.chapters']);
        $purchasedTtsProducts = $user->completedTtsProductPurchases()
            ->with('product.linkedAudiobook.chapters')
            ->latest('purchased_at')
            ->get()
            ->pluck('product')
            ->filter()
            ->unique('id')
            ->values();

        $subscriptionProducts = collect();
        $subscriptionTtsProducts = collect();

        if ($activePlan?->includesMusicLibrary()) {
            $subscriptionProducts = Product::with(['category', 'media', 'linkedAudiobook.chapters'])
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();
        }

        if ($activePlan?->includesAllTtsCategories()) {
            $subscriptionTtsProducts = TtsAudioProduct::with('linkedAudiobook.chapters')
                ->active()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();
        } elseif ($activePlan && $activePlan->getIncludedTtsCategories() !== []) {
            $subscriptionTtsProducts = TtsAudioProduct::with('linkedAudiobook.chapters')
                ->active()
                ->whereIn('category', $activePlan->getIncludedTtsCategories())
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();
        }

        $libraryProducts = $purchasedProducts
            ->merge($subscriptionProducts)
            ->unique('id')
            ->values();

        $libraryTtsProducts = $purchasedTtsProducts
            ->merge($subscriptionTtsProducts)
            ->unique('id')
            ->values();

        $readingProducts = $libraryProducts
            ->filter(fn (Product $product) => $product->pdf_file_path || $product->html_book_path || $product->html_book_url)
            ->values();
        $readingTtsProducts = $libraryTtsProducts
            ->filter(fn (TtsAudioProduct $product) => $product->pdf_file_path || $product->html_book_path || $product->html_book_url)
            ->values();

        return view('account.library', compact(
            'user',
            'libraryProducts',
            'libraryTtsProducts',
            'readingProducts',
            'readingTtsProducts',
            'activeSubscription',
            'activePlan'
        ));
    }

    public function downloadProductPdf(Product $product)
    {
        $user = Auth::user();
        abort_unless($user->hasMusicProductAccess($product->id), 403);
        abort_unless($product->pdf_file_path, 404);

        $path = $this->resolveBookAssetPath($product->pdf_file_path);
        abort_unless($path && is_file($path), 404);

        return response()->download($path, ($product->slug ?: 'product-' . $product->id) . '.pdf');
    }

    public function readProductHtml(Product $product, ?string $assetPath = null)
    {
        $user = Auth::user();
        abort_unless($user->hasMusicProductAccess($product->id), 403);

        if ($product->html_book_url && ! $product->html_book_path && ! $assetPath) {
            return redirect()->away($product->html_book_url);
        }

        abort_unless($product->html_book_path, 404);

        return $this->serveHtmlBookAsset(
            $product->html_book_path,
            $assetPath,
            route('account.library.products.read', ['product' => $product]) . '/'
        );
    }

    public function downloadTtsProductPdf(TtsAudioProduct $product)
    {
        $user = Auth::user();
        abort_unless($user->hasTtsProductAccess($product->id) || $user->hasTtsCategoryAccess($product->category), 403);
        abort_unless($product->pdf_file_path, 404);

        $path = $this->resolveBookAssetPath($product->pdf_file_path);
        abort_unless($path && is_file($path), 404);

        return response()->download($path, $product->slug . '.pdf');
    }

    public function readTtsProductHtml(TtsAudioProduct $product, ?string $assetPath = null)
    {
        $user = Auth::user();
        abort_unless($user->hasTtsProductAccess($product->id) || $user->hasTtsCategoryAccess($product->category), 403);

        if ($product->html_book_url && ! $product->html_book_path && ! $assetPath) {
            return redirect()->away($product->html_book_url);
        }

        abort_unless($product->html_book_path, 404);

        return $this->serveHtmlBookAsset(
            $product->html_book_path,
            $assetPath,
            route('account.library.tts-products.read', ['product' => $product]) . '/'
        );
    }

    public function orders()
    {
        $user = Auth::user();
        $orders = $user->orders()->latest()->paginate(10);

        return view('account.orders', compact('user', 'orders'));
    }

    public function profile()
    {
        $user = Auth::user();
        $this->studentPricing->refreshUserStatus($user);
        $user->load(['passkeys' => fn ($query) => $query->latest()]);

        return view('account.profile', compact('user'));
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
        ]);

        $user->update([
            'name'  => $request->name,
            'email' => $request->email,
        ]);

        return redirect()->route('account.profile')->with('success', 'Profile updated successfully.');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password'      => 'required',
            'password'              => 'required|min:8|confirmed',
            'password_confirmation' => 'required',
        ]);

        $user = Auth::user();

        if (! Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        $user->update(['password' => Hash::make($request->password)]);

        return redirect()->route('account.profile')->with('success', 'Password changed successfully.');
    }

    public function submitStudentVerification(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'student_institution' => 'required|string|max:255',
            'student_id_number' => 'required|string|max:255',
            'student_document' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        $data = [
            'student_status' => 'pending',
            'student_expires_at' => now()->addDays(14),
            'student_verified_at' => null,
            'student_reviewed_at' => null,
            'student_reviewed_by' => null,
            'student_institution' => $request->student_institution,
            'student_id_number' => $request->student_id_number,
            'student_review_notes' => null,
        ];

        if ($request->hasFile('student_document')) {
            if ($user->student_document_path) {
                Storage::disk('public')->delete($user->student_document_path);
            }

            $data['student_document_path'] = $request->file('student_document')
                ->store('student-verification/' . $user->id, 'public');
            $data['student_document_uploaded_at'] = now();
        }

        $user->update($data);

        return redirect()->route('account.profile')->with('success', 'Student verification submitted. Student pricing is active while your request is under review.');
    }

    public function affiliate()
    {
        $user = Auth::user();
        $affiliateProfile = $user->affiliateProfile()
            ->with(['conversions', 'payouts'])
            ->first();

        $stats = [
            'clicks' => $affiliateProfile?->clicks()->count() ?? 0,
            'conversions' => $affiliateProfile?->conversions()->count() ?? 0,
            'gross_sales' => (float) ($affiliateProfile?->conversions()->sum('gross_amount') ?? 0),
            'approved_commissions' => (float) ($affiliateProfile?->conversions()->where('status', 'approved')->sum('commission_amount') ?? 0),
            'paid_commissions' => (float) ($affiliateProfile?->conversions()->where('status', 'paid')->sum('commission_amount') ?? 0),
        ];

        $recentConversions = $affiliateProfile
            ? $affiliateProfile->conversions()->latest()->limit(10)->get()
            : collect();
        $recentPayouts = $affiliateProfile
            ? $affiliateProfile->payouts()->latest('paid_at')->limit(10)->get()
            : collect();

        return view('account.affiliate', compact('user', 'affiliateProfile', 'stats', 'recentConversions', 'recentPayouts'));
    }

    public function applyAffiliate(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'payout_email' => 'nullable|email|max:255',
            'application_notes' => 'nullable|string|max:2000',
        ]);

        $this->affiliateService->apply($user, $request->only('payout_email', 'application_notes'));

        return redirect()->route('account.affiliate')->with('success', 'Affiliate application saved. Once approved, your custom commission rate and referral link will be active.');
    }

    private function resolveBookAssetPath(string $path): ?string
    {
        $path = ltrim($path, '/');
        $candidates = [
            base_path($path),
            public_path($path),
            storage_path('app/public/' . $path),
        ];

        foreach ($candidates as $candidate) {
            $real = realpath($candidate);
            if (! $real) {
                continue;
            }

            $allowedRoots = [
                realpath(base_path('ebook')),
                realpath(public_path()),
                realpath(storage_path('app/public')),
            ];

            foreach (array_filter($allowedRoots) as $root) {
                if (str_starts_with($real, $root . DIRECTORY_SEPARATOR) || $real === $root) {
                    return $real;
                }
            }
        }

        return null;
    }

    private function isPathInsideBookFolder(string $resolvedPath, string $htmlBookPath): bool
    {
        $bookIndex = $this->resolveBookAssetPath($htmlBookPath);
        if (! $bookIndex) {
            return false;
        }

        $bookRoot = realpath(dirname($bookIndex));
        return $bookRoot
            && (str_starts_with($resolvedPath, $bookRoot . DIRECTORY_SEPARATOR) || $resolvedPath === $bookRoot);
    }

    private function serveHtmlBookAsset(string $htmlBookPath, ?string $assetPath, string $baseUrl)
    {
        $path = $assetPath
            ? $this->resolveBookAssetPath(dirname($htmlBookPath) . '/' . $assetPath)
            : $this->resolveBookAssetPath($htmlBookPath);

        abort_unless($path && is_file($path), 404);

        if (! $this->isPathInsideBookFolder($path, $htmlBookPath)) {
            abort(404);
        }

        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'html') {
            return response()->file($path);
        }

        $html = file_get_contents($path);

        if (str_contains($html, '<head>')) {
            $html = str_replace('<head>', '<head><base href="' . e($baseUrl) . '">', $html);
        }

        return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
