<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Services\AlertService;

class CpuController extends Controller
{
    private AlertService $alertService;

    public function __construct(AlertService $alertService)
    {
        $this->alertService = $alertService;
    }

    private function now(): string
    {
        return Carbon::now()->format('D, d M Y H:i:s') . ' CET';
    }

    private function getCpuUsage(): float
    {
        $output = shell_exec("top -l 2 -n 0 | grep 'CPU usage' | tail -1");
        preg_match('/(\d+\.?\d*)% user/', $output ?? '', $m);
        preg_match('/(\d+\.?\d*)% sys/',  $output ?? '', $s);
        return round(floatval($m[1] ?? 0) + floatval($s[1] ?? 0), 2);
    }

    public function cpu(Request $request)
    {
        try {
            if ($request->query('force_error') === 'true') {
                throw new \Exception('Unable to read CPU metrics');
            }
            $usage = $this->getCpuUsage();
            $alert = $this->alertService->checkAndAlert('CPU', $usage, gethostname());
            $data  = [
                'total_usage_percent' => $usage,
                'logical_cores'       => intval(shell_exec('sysctl -n hw.logicalcpu')),
                'physical_cores'      => intval(shell_exec('sysctl -n hw.physicalcpu')),
                'checked_at'          => $this->now(),
                'alert_triggered'     => $alert['triggered'],
            ];
            if ($alert['incident']) $data['incident'] = $alert['incident'];
            return response()->json($data, 200);
        } catch (\Exception $e) {
            return response()->json([
                'error'      => $e->getMessage(),
                'status'     => 500,
                'checked_at' => $this->now(),
            ], 500);
        }
    }
}
