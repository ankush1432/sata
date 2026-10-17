<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $logs = AdminAuditLog::with('admin:id,name,email,role')
            ->latest()
            ->paginate($request->input('per_page', 25));

        return $this->successResponse($logs);
    }
}
