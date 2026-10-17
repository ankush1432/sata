<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HealthController extends Controller
{
    /**
     * Check system, database, and storage health.
     */
    public function index(): JsonResponse
    {
        $services = [
            'database' => 'unknown',
            'storage' => 'unknown',
            'cache' => 'unknown',
        ];

        $isHealthy = true;

        // Check database
        try {
            DB::connection()->getPdo();
            $services['database'] = 'connected';
        } catch (Exception $e) {
            $services['database'] = 'error: ' . $e->getMessage();
            $isHealthy = false;
        }

        // Check private evidence storage
        try {
            Storage::disk('private_evidence')->put('.health_check', 'ok');
            Storage::disk('private_evidence')->delete('.health_check');
            $services['storage'] = 'accessible';
        } catch (Exception $e) {
            $services['storage'] = 'error: ' . $e->getMessage();
            $isHealthy = false;
        }

        // Check cache
        try {
            Cache::put('health_check', 'ok', 10);
            $cached = Cache::get('health_check');
            $services['cache'] = ($cached === 'ok') ? 'operational' : 'degraded';
        } catch (Exception $e) {
            $services['cache'] = 'error: ' . $e->getMessage();
            $isHealthy = false;
        }

        $data = [
            'status' => $isHealthy ? 'healthy' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'services' => $services,
            'version' => '1.0.0',
        ];

        if (!$isHealthy) {
            return $this->errorResponse('One or more services are degraded', $data, 503);
        }

        return $this->successResponse($data, 'SATA Backend API is healthy');
    }
}
