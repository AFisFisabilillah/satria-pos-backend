<?php

namespace App\Http\Controllers;

use App\Http\Requests\MemberRequest;
use App\Http\Resources\MemberResource;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MemberController extends Controller
{
    public function index(Request $request)
    {
        $name = $request->query('name');
        $memberCode = $request->query('member_code');
        $active = $request->query('active');
        $minTotalSpent = $request->query('min_total_spent');
        $maxTotalSpent = $request->query('max_total_spent');
        $size = $request->query('size', 10);

        $query = Member::query();

        $query->when($name, function ($q, $name) {
            $q->where('name', 'like', "%{$name}%");
        });

        $query->when($memberCode, function ($q, $memberCode) {
            $q->where('member_code', 'like', "%{$memberCode}%");
        });

        $query->when($active !== null, function ($q) use ($active) {
            $q->where('active', filter_var($active, FILTER_VALIDATE_BOOLEAN));
        });

        $query->when($minTotalSpent !== null, function ($q) use ($minTotalSpent) {
            $q->where('total_spent', '>=', $minTotalSpent);
        });

        $query->when($maxTotalSpent !== null, function ($q) use ($maxTotalSpent) {
            $q->where('total_spent', '<=', $maxTotalSpent);
        });

        return MemberResource::collection($query->paginate($size));
    }

    public function store(MemberRequest $request)
    {
        $member = Member::create(array_merge($request->validated(), [
            'member_code' => 'MEM-' . strtoupper(Str::random(8)),
            'total_spent' => $request->total_spent ?? 0,
            'active' => $request->has('active') ? $request->active : true,
        ]));

        return new MemberResource($member);
    }

    public function show(Member $member)
    {
        return new MemberResource($member);
    }

    public function update(MemberRequest $request, Member $member)
    {
        $member->update($request->validated());

        return new MemberResource($member);
    }

    public function destroy(Member $member)
    {
        $member->delete();

        return response()->json([
            'message' => 'Member deleted successfully.'
        ]);
    }
}
