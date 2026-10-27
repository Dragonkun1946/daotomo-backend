<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Services\User\SkinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SkinController extends Controller
{
    protected SkinService $skinService;

    public function __construct(SkinService $skinService)
    {
        $this->skinService = $skinService;
    }

    /**
     * Upload or update Minecraft skin for user.
     */
    public function updateSkin(Request $request): JsonResponse
    {
        $user = $request->user();
        $skinData = $request->input('skinData');
        $skinUrl = $request->input('skinUrl');

        $result = $this->skinService->updateUserSkin($user, $skinData, $skinUrl);

        return response()->json($result['data'], $result['status']);
    }
}
