<?php

namespace App\Http\Controllers\Api\Checkin;

use App\Http\Controllers\Controller;
use App\Services\Checkin\CheckinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckinController extends Controller
{
    protected CheckinService $checkinService;

    public function __construct(CheckinService $checkinService)
    {
        $this->checkinService = $checkinService;
    }

    /**
     * Daily checkin endpoint.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $result = $this->checkinService->checkin($user);
        return response()->json($result['data'], $result['status']);
    }

    /**
     * Get current user checkin streak & history.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $result = $this->checkinService->getHistory($user);
        return response()->json($result['data'], $result['status']);
    }
}
