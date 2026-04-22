
<?php

 

namespace App\Http\Controllers;

 

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Services\AlertService;

 

class AllController extends Controller
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
        preg_match('/LoadPercentage=(\d+)/', shell_exec('wmic cpu get LoadPercentage /Value') ?? '', $m);
        return floatval($m[1] ?? 0);
    }

 

    private function getMemoryInfo(): array
    {
        $total = intval(shell_exec('wmic ComputerSystem get TotalPhysicalMemory /Value | findstr ='));
        preg_match('/FreePhysicalMemory=(\d+)/', shell_exec('wmic OS get FreePhysicalMemory /Value') ?? '', $m);
        $free = intval($m[1] ?? 0) * 1024;
        $used = $total - $free;
        $gb   = 1024 ** 3;
        return [
            'total_gb'     => round($total / $gb, 2),
            'used_gb'      => round($used  / $gb, 2),
            'available_gb' => round($free  / $gb, 2),
            'used_percent' => round(($used / $total) * 100, 2),
        ];
    }

 

    private function getDiskInfo(): array
    {
        $total = disk_total_space('C:\\');
        $free  = disk_free_space('C:\\');
        $used  = $total - $free;
        $gb    = 1024 ** 3;
        return [
            'total_gb'     => round($total / $gb, 2),
            'used_gb'      => round($used  / $gb, 2),
            'free_gb'      => round($free  / $gb, 2),
            'used_percent' => round(($used / $total) * 100, 2),
        ];
    }

 

    public function all(Request $request)
    {
        try {
            if ($request->query('force_error') === 'true') {
                throw new \Exception('Unable to read system metrics');
            }
            $host = gethostname();
            $cpu  = $this->getCpuUsage();
            $mem  = $this->getMemoryInfo();
            $disk = $this->getDiskInfo();

 

            $ca = $this->alertService->checkAndAlert('CPU',    $cpu,                 $host);
            $ma = $this->alertService->checkAndAlert('RAM',    $mem['used_percent'],  $host);
            $da = $this->alertService->checkAndAlert('DISQUE', $disk['used_percent'], $host);

 

            $ci = [
                'total_usage_percent' => $cpu,
                'logical_cores'       => intval(shell_exec('wmic cpu get NumberOfLogicalProcessors /Value | findstr =')),
                'physical_cores'      => intval(shell_exec('wmic cpu get NumberOfCores /Value | findstr =')),
                'checked_at'          => $this->now(),
                'alert_triggered'     => $ca['triggered'],
            ];
            if ($ca['incident']) $ci['incident'] = $ca['incident'];

 

            $mi = array_merge($mem, ['checked_at' => $this->now(), 'alert_triggered' => $ma['triggered']]);
            if ($ma['incident']) $mi['incident'] = $ma['incident'];

 

            $di = array_merge($disk, ['checked_at' => $this->now(), 'alert_triggered' => $da['triggered']]);
            if ($da['incident']) $di['incident'] = $da['incident'];

 

            return response()->json([
                'host_info'   => [
                    'status'     => 'UP',
                    'hostname'   => $host,
                    'os'         => strtolower(PHP_OS_FAMILY),
                    'platform'   => php_uname('r'),
                    'checked_at' => $this->now(),
                ],
                'cpu_info'    => $ci,
                'memory_info' => $mi,
                'disk_info'   => $di,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error'      => $e->getMessage(),
                'status'     => 500,
                'checked_at' => $this->now(),
            ], 500);
        }
    }
}

