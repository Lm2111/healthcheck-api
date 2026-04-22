<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Services\AlertService;

class DiskController extends Controller
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

    private function getDiskInfo(): array
    {
        $drive = PHP_OS_FAMILY === 'Windows' ? 'C:\\' : '/';
        $total = disk_total_space($drive);
        $free  = disk_free_space($drive);
        $used  = $total - $free;
        $gb    = 1024 ** 3;
        return [
            'total_gb'     => round($total / $gb, 2),
            'used_gb'      => round($used  / $gb, 2),
            'free_gb'      => round($free  / $gb, 2),
            'used_percent' => round(($used / $total) * 100, 2),
        ];
    }

    public function disk(Request $request)
    {
        try {
            if ($request->query('force_error') === 'true') {
                throw new \Exception('Unable to read disk metrics');
            }
            $disk  = $this->getDiskInfo();
            $alert = $this->alertService->checkAndAlert('DISQUE', $disk['used_percent'], gethostname());
            $data  = array_merge($disk, [
                'checked_at'      => $this->now(),
                'alert_triggered' => $alert['triggered'],
            ]);
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
