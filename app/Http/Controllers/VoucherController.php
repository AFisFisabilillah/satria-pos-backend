<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateVoucherRequest;
use App\Http\Requests\VoucherRequest;
use App\Http\Resources\VoucherResource;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VoucherController extends Controller
{
    public function index()
    {
        $search = request()->query('search');
        $active = request()->query('active');
        $maxExpired = request()->query('maxExpired');
        $minExpired = request()->query('minExpired');
        $minQuota = request()->query('minQuota');
        $maxQuota = request()->query('maxQuota');
        $size = request()->query('size',10);

        $query = Voucher::query();
        $query->when($active, function ($q, $active) {
            return $q->where('active', $active);
        });
        $query->when($maxExpired, function ($q, $maxExpired) {
            return $q->where('expired_at', '<=', $maxExpired);
        });

        $query->when($minExpired, function ($q, $minExpired) {
            return $q->where('expired_at', '>=', $minExpired);
        });
        $query->when($minQuota, function ($q, $minQuota) {
            return $q->where('quota', '>=', $minQuota);
        });
        $query->when($maxQuota, function ($q, $maxQuota) {
            return $q->where('quota', '<=', $maxQuota);
        });
        $query->when($search, function ($q, $search) {
            return $q->where('name', 'like', '%' . $search );
        });

        return VoucherResource::collection($query->paginate($size));
    }

    public function store(VoucherRequest $request)
    {
        return new VoucherResource(Voucher::create($request->validated()));
    }

    public function show(Voucher $voucher)
    {
        return new VoucherResource($voucher);
    }

    public function update(UpdateVoucherRequest $request, Voucher $voucher)
    {
        $voucher->update($request->validated());

        return new VoucherResource($voucher);
    }

    public function destroy(Voucher $voucher)
    {
        $voucher->delete();

        return response()->json([
            "status"=>"success",
            "message"=>"succes delete voucher"
        ]);
    }

    public function toggleActive(Voucher $voucher){
        $voucher->active= !$voucher->active;
        $voucher->save();
        return response()->json([
            "status"=>"success",
            "message"=>"succes active voucher"
        ]);
    }

    public function bulkToggleActive(Request $request){
        $request->validate([
            "ids" => "required",
            "id.*" => "exists:vouchers,id",
        ]);

        Voucher::whereIn('id', $request->ids)
            ->update(['active' => DB::raw('NOT active')]);

        return response()->json([
            'message' => 'Products status toggled successfully.'
        ]);
    }
}
