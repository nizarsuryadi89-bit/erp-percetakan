<?php

namespace App\Http\Controllers;

use App\Actions\CreateCustomer;
use App\Http\Requests\StoreCustomerRequest;
use App\Models\Customer;
use App\Support\DeskprintAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    public function index(Request $request, DeskprintAccess $access): JsonResponse
    {
        $data = $request->validate(['branch_id' => ['required', 'integer'], 'search' => ['nullable', 'string', 'max:100']]);
        $branch = $access->branch($request->user(), (int) $data['branch_id']);
        $search = $data['search'] ?? '';
        $customers = Customer::where('branch_id', $branch->id)->where(function (Builder $query) use ($search): void {
            $query->where('name', 'like', '%'.$search.'%')->orWhere('phone', 'like', '%'.$search.'%');
        })->orderBy('name')->orderBy('id')->limit(30)->get(['id', 'name', 'phone', 'address']);

        return response()->json(['customers' => $customers]);
    }

    public function store(StoreCustomerRequest $request, CreateCustomer $create): JsonResponse
    {
        return response()->json(['customer' => $create->handle($request->user(), $request->validated())->only(['id', 'name', 'phone', 'address']), 'request_key' => (string) Str::uuid()]);
    }
}
