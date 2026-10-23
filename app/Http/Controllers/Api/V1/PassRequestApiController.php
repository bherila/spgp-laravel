<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PassRequests\PassRequestOperations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class PassRequestApiController extends Controller
{
    protected bool $admin = false;

    public function __invoke(Request $request, PassRequestOperations $operations): JsonResponse
    {
        $input = $request->all();
        foreach (['id', 'season_id'] as $selector) {
            if ($request->route($selector) !== null) {
                $input[$selector] = $request->route($selector);
            }
        }
        $operation = $request->route('operation');

        return response()->json(
            $operations->execute($operation, $input, $this->admin),
            $operation === 'create_pass_request' ? 201 : 200,
            ['Cache-Control' => 'no-store'],
        );
    }
}
