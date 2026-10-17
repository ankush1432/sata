<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Notification::with(['user:id,name,email', 'device:id,device_name,device_model']);

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        $notifications = $query->latest()->paginate($request->input('per_page', 25));

        return $this->successResponse($notifications);
    }

    public function show(int $id): JsonResponse
    {
        $notification = Notification::with(['user', 'device'])->find($id);

        if (! $notification) {
            return $this->errorResponse('Notification not found.', null, 404);
        }

        return $this->successResponse(['notification' => $notification]);
    }
}
