<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\Checkin\CheckinController as ApiCheckinController;
use Illuminate\Http\Request;

/**
 * Top-level CheckinController facade.
 * Coordinates Daily Checkin domain with 100% backwards compatibility.
 */
class CheckinController extends Controller
{
    public function store(Request $request)
    {
        return app(ApiCheckinController::class)->store($request);
    }

    public function me(Request $request)
    {
        return app(ApiCheckinController::class)->me($request);
    }
}
