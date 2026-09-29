<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\AddressResource;
use App\Models\CustomerAddress;
use App\Services\Api\ApiCatalog;
use App\Services\Api\ApiFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressController extends ApiController
{
    private const RULES = [
        'label' => 'sometimes|nullable|string|max:60',
        'receiver_name' => 'required|string|max:255',
        'receiver_phone' => 'required|string|max:20',
        'address' => 'required|string|max:500',
        'city' => 'required|string|max:100',
        'province' => 'required|string|max:100',
        'postal_code' => 'nullable|string|max:20',
        'shipping_destination_id' => 'nullable|string|max:100',
        'latitude' => 'nullable|numeric',
        'longitude' => 'nullable|numeric',
        'is_default' => 'sometimes|boolean',
    ];

    public function index(Request $request): JsonResponse
    {
        $filter = ApiCatalog::addresses();
        $query = CustomerAddress::where('customer_id', $request->user()->id);
        $paginator = $filter->paginate($query, $request);

        return $this->paged($paginator, AddressResource::collection($paginator->getCollection()), 'OK', $filter, $request);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->idempotent($request, function () use ($request): JsonResponse {
            $data = CustomerAddress::normalize($request->validate(self::RULES));
            $customerId = (int) $request->user()->id;

            $address = DB::transaction(function () use ($data, $customerId): CustomerAddress {
                $isFirst = ! CustomerAddress::where('customer_id', $customerId)->exists();

                if (($data['is_default'] ?? false) || $isFirst) {
                    CustomerAddress::where('customer_id', $customerId)->update(['is_default' => false]);
                }

                return CustomerAddress::create($data + ['customer_id' => $customerId, 'is_default' => (bool) ($data['is_default'] ?? $isFirst)]);
            });

            $this->markResource($request, 'customer_address', (int) $address->id);

            return $this->created(new AddressResource($address->fresh()), 'Alamat ditambahkan');
        });
    }

    public function show(Request $request, int $address): JsonResponse
    {
        return $this->ok(new AddressResource($this->owned($request, $address)));
    }

    public function update(Request $request, int $address): JsonResponse
    {
        $model = $this->owned($request, $address);
        $data = CustomerAddress::normalize($request->validate(self::RULES));

        DB::transaction(function () use ($model, $data, $request): void {
            if ((bool) ($data['is_default'] ?? false)) {
                CustomerAddress::where('customer_id', $request->user()->id)->update(['is_default' => false]);
            }

            $model->fill($data)->save();
        });

        return $this->ok(new AddressResource($model->fresh()), 'Alamat diperbarui');
    }

    public function destroy(Request $request, int $address): JsonResponse
    {
        $model = $this->owned($request, $address);
        $wasDefault = (bool) $model->is_default;
        $model->delete();

        if ($wasDefault) {
            $replacement = CustomerAddress::where('customer_id', $request->user()->id)->oldest('id')->first();

            if ($replacement !== null) {
                $replacement->forceFill(['is_default' => true])->save();
            }
        }

        return $this->ok(null, 'Alamat dihapus');
    }

    private function owned(Request $request, int $address): CustomerAddress
    {
        $model = CustomerAddress::where('customer_id', $request->user()->id)->find($address);

        $this->abortUnlessOwned($model !== null);

        return $model;
    }
}
