<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;

abstract class Controller
{
    use AuthorizesRequests;

    protected function sendResponse($data = [], $message = 'Success', $code = 200): JsonResponse
    {
        if ($data instanceof ResourceCollection) {
            $response = $data->response()->getData(true);

            return response()->json([
                'success' => true,
                'message' => $message,
                'data'    => $response['data'],
                'meta'    => $response['meta'] ?? null,
                'links'   => $response['links'] ?? null,
            ], $code);
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }



    protected function sendError($message = 'Error', $errors = [], $code = 404): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if (!empty($errors)) {
            $response['data'] = $errors;
        }

        return response()->json($response, $code);
    }
}
