<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class AlertService
{
    private string $monitoringUrl;
    private string $bearerToken;
    private string $applicationId;
    private int    $alertThreshold = 30;

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

    private function nowIso(): string
    {
        return Carbon::now()->format('Y-m-d\TH:i:s');
    }

    private function getSeverity(float $percent): string
    {
        if ($percent > 90) return 'CRITICAL';
        if ($percent > 60) return 'HIGH';
        return 'LOW';
    }

    public function createIncident(string $title, string $desc, string $severity): array
    {
        $response = Http::withToken($this->bearerToken)
            ->post($this->monitoringUrl . '/incidents', [
                'title'          => $title,
                'description'    => $desc,
                'application_id' => $this->applicationId,
                'status'         => 'OPEN',
                'severity'       => $severity,
                'start_date'     => $this->nowIso(),
            ]);
        $data = $response->json();
        return [
            'id'       => $data['id'] ?? null,
            'severity' => $severity,
            'message'  => 'Incident created on monitoring platform',
        ];
    }

    public function checkAndAlert(string $metric, float $value, string $hostname): array
    {
        if ($value > $this->alertThreshold) {
            $severity = $this->getSeverity($value);
            $title    = "ALERTE {$metric} — Utilisation a {$value}%";
            $desc     = "Le serveur {$hostname} a detecte une utilisation {$metric} anormale de {$value}% a " . $this->now() . ".";
            return ['triggered' => true, 'incident' => $this->createIncident($title, $desc, $severity)];
        }
        return ['triggered' => false, 'incident' => null];
    }
}
