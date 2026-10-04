<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Services\Checkout\CheckoutFlowService;
use App\Services\Checkout\CheckoutPageService;
use App\Services\Checkout\StorefrontCheckoutOrderService;
use App\Services\Checkout\StorefrontDeliveryService;
use App\Services\Checkout\StorefrontPaymentMethodService;
use App\Services\Payment\RazorpayPaymentService;
use App\Services\Storefront\NavigationService;
use App\Services\Storefront\StorefrontCustomerContext;
use App\Services\System\SystemSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CheckoutFlowService $checkout,
        private readonly CheckoutPageService $checkoutPage,
        private readonly StorefrontCheckoutOrderService $checkoutOrders,
        private readonly StorefrontDeliveryService $delivery,
        private readonly StorefrontPaymentMethodService $payments,
        private readonly NavigationService $navigation,
        private readonly StorefrontCustomerContext $customerContext,
        private readonly SystemSettingService $systemSettings,
        private readonly RazorpayPaymentService $razorpay,
    ) {}

    public function index(Request $request): RedirectResponse|View
    {
        if (! $this->checkout->hasCartItems($request)) {
            return redirect()
                ->route('storefront.cart')
                ->with('error', 'Your cart is empty.');
        }

        if ($request->query->has('shop') && ! $this->checkout->selectShop($request, (int) $request->query('shop'))) {
            return redirect()->route('storefront.cart')->with('error', 'Please choose a valid shop from your cart.');
        }

        if ($this->checkout->selectedShopId($request) === null) {
            return redirect()->route('storefront.cart')->with('error', 'Please choose a shop to continue checkout.');
        }

        $this->checkout->rememberIntent($request);

        $customer = $this->customerContext->user($request);

        if ($customer === null) {
            return redirect()->route('storefront.checkout.account');
        }

        $this->checkout->forgetIntent($request);

        return view('storefront.pages.checkout', [
            ...$this->checkoutPage->pageData($request, $customer),
            'customer' => $customer,
            'storefrontNavigationCategories' => $this->navigation->getMarketplaceCategories(),
        ]);
    }

    public function account(Request $request): View|RedirectResponse
    {
        if (! $this->checkout->hasCartItems($request)) {
            return redirect()
                ->route('storefront.cart')
                ->with('error', 'Your cart is empty.');
        }

        if ($this->checkout->selectedShopId($request) === null) {
            return redirect()->route('storefront.cart')->with('error', 'Please choose a shop to continue checkout.');
        }

        $this->checkout->rememberIntent($request);

        if ($this->checkout->isCustomer($request)) {
            return redirect()->route(CheckoutFlowService::CHECKOUT_ROUTE);
        }

        return view('storefront.pages.customer-login', [
            'checkoutMode' => true,
            'storefrontNavigationCategories' => $this->navigation->getMarketplaceCategories(),
        ]);
    }

    public function address(Request $request): RedirectResponse
    {
        return redirect()->route(CheckoutFlowService::CHECKOUT_ROUTE);
    }

    public function fulfillment(Request $request): JsonResponse|RedirectResponse
    {
        $customer = $this->customerContext->user($request);
        abort_unless($customer !== null, 403);
        $globalCustomer = $this->customerContext->customer($request);
        abort_unless($globalCustomer instanceof Customer, 403);

        if (! $this->checkout->hasCheckoutItems($request)) {
            return response()->json([
                'ok' => false,
                'message' => 'Your cart is empty.',
            ], 422);
        }

        $data = $request->validate([
            'fulfillment' => ['required', Rule::in([
                StorefrontDeliveryService::FULFILLMENT_DELIVERY,
                StorefrontDeliveryService::FULFILLMENT_PICKUP,
            ])],
        ]);

        $this->delivery->select($request, $data['fulfillment']);
        $pageData = $this->checkoutPage->pageData($request, $customer);

        if ($pageData['selectedFulfillment'] !== $data['fulfillment']) {
            throw ValidationException::withMessages([
                'fulfillment' => 'That fulfilment option is not available for this shop.',
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'selected_fulfillment' => $pageData['selectedFulfillment'],
                'shipping_options' => $pageData['shippingOptions'],
                'shipping' => $pageData['shippingTotal'],
                'total' => $pageData['cartData']['total'],
                'payment_methods' => $pageData['paymentMethods'],
                'selected_payment_method' => $pageData['selectedPaymentMethod'],
                'payment_unavailable_message' => $pageData['paymentUnavailableMessage'],
                'can_place_order' => $pageData['canPlaceOrder'],
            ]);
        }

        return redirect()->route('storefront.checkout');
    }

    public function placeOrder(Request $request): RedirectResponse
    {
        $customer = $this->customerContext->user($request);
        abort_unless($customer !== null, 403);
        $globalCustomer = $this->customerContext->customer($request);
        abort_unless($globalCustomer instanceof Customer, 403);

        if (! $this->checkout->hasCheckoutItems($request)) {
            return redirect()
                ->route('storefront.cart')
                ->with('error', 'Your cart is empty.');
        }

        $data = $request->validate([
            'address_id' => ['nullable', 'integer', 'exists:customer_addresses,id'],
            'billing_same_as_delivery' => ['nullable', 'boolean'],
            'billing_address_id' => ['nullable', 'integer', 'exists:customer_addresses,id'],
            'shipping_method' => ['required', Rule::in([
                StorefrontDeliveryService::FULFILLMENT_DELIVERY,
                StorefrontDeliveryService::FULFILLMENT_PICKUP,
                StorefrontDeliveryService::LEGACY_FULFILLMENT_STANDARD,
            ])],
            'payment_method' => ['required', Rule::in([
                StorefrontPaymentMethodService::PAYMENT_CASH_ON_DELIVERY,
                StorefrontPaymentMethodService::PAYMENT_CASH_AT_SHOP,
                StorefrontPaymentMethodService::PAYMENT_MERCHANT_UPI,
                StorefrontPaymentMethodService::PAYMENT_ONLINE,
            ])],
            'upi_reference' => [
                Rule::requiredIf(fn (): bool => $request->input('payment_method') === StorefrontPaymentMethodService::PAYMENT_MERCHANT_UPI),
                'nullable',
                'string',
                'max:100',
                'regex:/^[A-Za-z0-9][A-Za-z0-9._-]{3,99}$/',
            ],
            'customer_order_note' => ['nullable', 'string', 'max:1000'],
            'browser_total' => ['nullable'],
        ]);

        $address = null;
        if (! empty($data['address_id'])) {
            $address = CustomerAddress::query()->findOrFail($data['address_id']);
            abort_unless($this->checkoutPage->addressBelongsToCustomer($address, $globalCustomer), 404);
            $request->session()->put(CheckoutPageService::SELECTED_ADDRESS_SESSION_KEY, $address->getKey());
        }

        $selectedFulfillment = $this->delivery->select($request, $data['shipping_method'])['selected'];
        $pageData = $this->checkoutPage->pageData($request, $customer);

        if ($pageData['selectedFulfillment'] !== $selectedFulfillment) {
            throw ValidationException::withMessages([
                'shipping_method' => 'That fulfilment option is not available for this shop.',
            ]);
        }

        $billingAddress = null;
        if ($selectedFulfillment === StorefrontDeliveryService::FULFILLMENT_DELIVERY) {
            $billingSameAsDelivery = (bool) ($data['billing_same_as_delivery'] ?? $this->checkoutPage->billingSameAsDelivery($request));

            if ($billingSameAsDelivery && $address instanceof CustomerAddress) {
                $billingAddress = $address;
                $request->session()->put(CheckoutPageService::BILLING_SAME_AS_DELIVERY_SESSION_KEY, true);
                $request->session()->forget(CheckoutPageService::SELECTED_BILLING_ADDRESS_SESSION_KEY);
            } else {
                $billingAddressId = (int) ($data['billing_address_id'] ?? 0);

                if ($billingAddressId <= 0) {
                    throw ValidationException::withMessages([
                        'billing_address_id' => 'Please select a billing address.',
                    ]);
                }

                $billingAddress = CustomerAddress::query()->findOrFail($billingAddressId);
                abort_unless($this->checkoutPage->addressBelongsToCustomer($billingAddress, $globalCustomer), 404);
                $request->session()->put(CheckoutPageService::BILLING_SAME_AS_DELIVERY_SESSION_KEY, false);
                $request->session()->put(CheckoutPageService::SELECTED_BILLING_ADDRESS_SESSION_KEY, $billingAddress->getKey());
            }
        }

        try {
            $order = $this->checkoutOrders->place(
                $request,
                $customer,
                $globalCustomer,
                $selectedFulfillment,
                $data['payment_method'],
                $billingAddress,
                $data['customer_order_note'] ?? null,
                $data['upi_reference'] ?? null,
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Storefront checkout order placement failed.', [
                'customer_id' => $customer->getKey(),
                'exception' => $exception,
            ]);

            return redirect()
                ->route('storefront.checkout')
                ->withInput()
                ->with('error', 'We could not place your order. Please review your cart and try again.');
        }

        if ($order->payment_method === StorefrontPaymentMethodService::PAYMENT_ONLINE) {
            $request->session()->put('storefront.razorpay.start_order', $order->getKey());
        }

        return redirect()->route('storefront.checkout.success', $order);
    }

    public function success(Request $request, Order $order): View
    {
        $customer = $this->customerContext->user($request);
        abort_unless($customer !== null, 403);

        $order->load(['customer', 'shop.city', 'items']);
        abort_unless((int) $order->customer?->user_id === (int) $customer->getKey(), 404);

        $razorpayCheckout = null;
        $razorpayError = null;
        if ((int) $request->session()->pull('storefront.razorpay.start_order') === (int) $order->getKey()) {
            try {
                $razorpayCheckout = $this->razorpay->initiate($order);
            } catch (ValidationException $exception) {
                $razorpayError = $exception->validator->errors()->first('payment');
            }
        }

        return view('storefront.pages.checkout-success', [
            'order' => $order,
            'customer' => $customer,
            'currency' => $this->systemSettings->currencyConfig(),
            'storefrontNavigationCategories' => $this->navigation->getMarketplaceCategories(),
            'razorpayCheckout' => $razorpayCheckout,
            'razorpayError' => $razorpayError,
        ]);
    }

    public function retryRazorpay(Request $request, Order $order): View
    {
        $this->authorizeCustomerOrder($request, $order);
        $order->load(['customer', 'shop.city', 'items']);

        return view('storefront.pages.checkout-success', [
            'order' => $order,
            'customer' => $this->customerContext->user($request),
            'currency' => $this->systemSettings->currencyConfig(),
            'storefrontNavigationCategories' => $this->navigation->getMarketplaceCategories(),
            'razorpayCheckout' => $this->razorpay->initiate($order),
            'razorpayError' => null,
        ]);
    }

    public function verifyRazorpay(Request $request, PaymentAttempt $paymentAttempt): JsonResponse
    {
        $this->authorizeCustomerAttempt($request, $paymentAttempt);
        $data = $request->validate([
            'razorpay_payment_id' => ['required', 'string', 'max:191'],
            'razorpay_order_id' => ['required', 'string', 'max:191'],
            'razorpay_signature' => ['required', 'string', 'max:512'],
        ]);
        $order = $this->razorpay->verify($paymentAttempt, $data['razorpay_payment_id'], $data['razorpay_order_id'], $data['razorpay_signature']);

        return response()->json(['ok' => true, 'redirect' => route('storefront.checkout.success', $order)]);
    }

    public function razorpayOutcome(Request $request, PaymentAttempt $paymentAttempt): JsonResponse
    {
        $this->authorizeCustomerAttempt($request, $paymentAttempt);
        $data = $request->validate(['status' => ['required', Rule::in([PaymentAttempt::FAILED, PaymentAttempt::ABANDONED])]]);
        $this->razorpay->recordClientOutcome($paymentAttempt, $data['status']);

        return response()->json(['ok' => true]);
    }

    private function authorizeCustomerAttempt(Request $request, PaymentAttempt $attempt): void
    {
        $attempt->loadMissing('order.customer');
        $customer = $this->customerContext->user($request);
        abort_unless($customer && (int) $attempt->order?->customer?->user_id === (int) $customer->getKey(), 404);
    }

    private function authorizeCustomerOrder(Request $request, Order $order): void
    {
        $order->loadMissing('customer');
        $customer = $this->customerContext->user($request);
        abort_unless($customer && (int) $order->customer?->user_id === (int) $customer->getKey(), 404);
    }
}
