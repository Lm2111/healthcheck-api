<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class IncidentController extends Controller
{
    private string $monitoringUrl;
    private string $bearerToken;
    private string $applicationId;

    public function __construct()
    {
        $this->monitoringUrl  = env('MONITORING_API_URL', 'https://monitoring-app.on-forge.com/api/v1');
        $this->bearerToken    = env('BEARER_TOKEN', '');
        $this->applicationId  = env('APPLICATION_ID', '');
    }

    private function now(): string
    {
        return Carbon::now()->format('D, d M Y H:i:s') . ' CET';
    }

    public function incidents(Request $request)
    {
        try {
            $response = Http::withToken($this->bearerToken)
                ->get($this->monitoringUrl . '/applications/' . $this->applicationId . '/incidents');
            $body = $response->json();

            // L'API retourne { data: [...] } ou directement un tableau
            $raw  = $body['data'] ?? $body;
            $list = is_array($raw) ? array_map(fn($i) => [
                'id'         => $i['id']         ?? null,
                'title'      => $i['title']      ?? '',
                'severity'   => $i['severity']   ?? '',
                'status'     => $i['status']     ?? '',
                'start_date' => $i['start_date'] ?? '',
            ], $raw) : [];

            return response()->json([
                'incidents'  => $list,
                'total'      => count($list),
                'checked_at' => $this->now(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error'      => $e->getMessage(),
                'status'     => 500,
                'checked_at' => $this->now(),
            ], 500);
        }
    }
}
