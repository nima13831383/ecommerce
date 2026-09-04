<?php

namespace App\Http\Controllers\Storefront;

use App\Exceptions\AddressValidationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\AddressRequest;
use App\Models\Address;
use App\Services\Addresses\AddressService;
use App\Services\Shipping\Data\WordpressShippingDataLoader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AddressController extends Controller
{
    public function __construct(
        private readonly AddressService $addresses,
        private readonly WordpressShippingDataLoader $geography,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $editing = $request->integer('edit') ?: null;
        $editingAddress = $editing ? $user->addresses()->find($editing) : null;
        $addressLocations = $user->addresses->mapWithKeys(function (Address $address): array {
            if ($address->province_id === null || $address->city_id === null) {
                return [$address->id => null];
            }

            return [$address->id => $this->addresses->resolveLocation((int) $address->province_id, (int) $address->city_id)];
        });

        return view('storefront.account.addresses', [
            'user' => $user,
            'addresses' => $user->addresses()->latest()->get(),
            'addressLocations' => $addressLocations,
            'editing' => $editingAddress,
            'provinces' => $this->geography->provinces(),
            'cities' => $editingAddress?->province_id
                ? $this->geography->cities((int) $editingAddress->province_id)
                : [],
            'title' => 'آدرس‌های من | لوکسیر',
        ]);
    }

    public function cities(int $province): JsonResponse
    {
        abort_unless($this->geography->provinceName($province) !== null, 404);

        return response()->json([
            'data' => collect($this->geography->cities($province))
                ->map(fn (string $name, int $id): array => ['id' => $id, 'name' => $name])
                ->values(),
        ]);
    }

    public function store(AddressRequest $request): RedirectResponse
    {
        try {
            $this->addresses->create($request->user(), $request->validated());

            return redirect()->route('storefront.account.addresses')->with('status', 'آدرس با موفقیت ذخیره شد.');
        } catch (AddressValidationException $exception) {
            return back()->withInput()->withErrors(['address' => $exception->getMessage()]);
        }
    }

    public function update(AddressRequest $request, Address $address): RedirectResponse
    {
        try {
            $this->addresses->update($request->user(), $address, $request->validated());

            return redirect()->route('storefront.account.addresses')->with('status', 'آدرس به‌روزرسانی شد.');
        } catch (AddressValidationException $exception) {
            return back()->withInput()->withErrors(['address' => $exception->getMessage()]);
        }
    }

    public function destroy(Request $request, Address $address): RedirectResponse
    {
        try {
            $this->addresses->delete($request->user(), $address);

            return redirect()->route('storefront.account.addresses')->with('status', 'آدرس حذف شد.');
        } catch (AddressValidationException $exception) {
            return back()->withErrors(['address' => $exception->getMessage()]);
        }
    }
}
