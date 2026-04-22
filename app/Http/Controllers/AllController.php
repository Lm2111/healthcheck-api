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
        if (PHP_OS_FAMILY === 'Windows') {
            preg_match('/LoadPercentage=(\d+)/', shell_exec('wmic cpu get LoadPercentage /Value') ?? '', $m);
            return floatval($m[1] ?? 0);
        }
        $out = shell_exec("top -l 2 -n 0 | grep 'CPU usage' | tail -1");
        preg_match('/(\d+\.?\d*)% user/', $out ?? '', $m);
        preg_match('/(\d+\.?\d*)% sys/',  $out ?? '', $s);
        return round(floatval($m[1] ?? 0) + floatval($s[1] ?? 0), 2);
    }

    private function getMemoryInfo(): array
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $total = intval(shell_exec('wmic ComputerSystem get TotalPhysicalMemory /Value | findstr ='));
            preg_match('/FreePhysicalMemory=(\d+)/', shell_exec('wmic OS get FreePhysicalMemory /Value') ?? '', $m);
            $free = intval($m[1] ?? 0) * 1024;
        } else {
            $total = intval(shell_exec('sysctl -n hw.memsize'));
            $vmStat = shell_exec('vm_stat');
            preg_match('/Pages free:\s+(\d+)/',     $vmStat ?? '', $f);
            preg_match('/Pages inactive:\s+(\d+)/', $vmStat ?? '', $i);
            $free = (intval($f[1] ?? 0) + intval($i[1] ?? 0)) * 4096;
        }
        $used = $total - $free;
        $gb   = 1024 ** 3;
        return [
            'total_gb'     => round($total / $gb, 2),
            'used_gb'      => round($used  / $gb, 2),
            'available_gb' => round($free  / $gb, 2),
            'used_percent' => round(($used / $total) * 100, 2),
        ];
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

    public function all(Request $request)
    {
        try {
            if ($request->query('force_error') === 'true') {
                throw new \Exception('Unable to read system metrics');
            }
            $host = gethostname();
            $cpu  = $this->getCpuUsage();
            $mem  = $this->getMemoryInfo();
            $disk = $this->getDiskInfo();

            $ca = $this->alertService->checkAndAlert('CPU',    $cpu,                 $host);
            $ma = $this->alertService->checkAndAlert('RAM',    $mem['used_percent'],  $host);
            $da = $this->alertService->checkAndAlert('DISQUE', $disk['used_percent'], $host);

            $ci = [
                'total_usage_percent' => $cpu,
                'logical_cores'       => PHP_OS_FAMILY === 'Windows'
                    ? intval(shell_exec('wmic cpu get NumberOfLogicalProcessors /Value | findstr ='))
                    : intval(shell_exec('sysctl -n hw.logicalcpu')),
                'physical_cores'      => PHP_OS_FAMILY === 'Windows'
                    ? intval(shell_exec('wmic cpu get NumberOfCores /Value | findstr ='))
                    : intval(shell_exec('sysctl -n hw.physicalcpu')),
                'checked_at'          => $this->now(),
                'alert_triggered'     => $ca['triggered'],
            ];
            if ($ca['incident']) $ci['incident'] = $ca['incident'];

            $mi = array_merge($mem, ['checked_at' => $this->now(), 'alert_triggered' => $ma['triggered']]);
            if ($ma['incident']) $mi['incident'] = $ma['incident'];

            $di = array_merge($disk, ['checked_at' => $this->now(), 'alert_triggered' => $da['triggered']]);
            if ($da['incident']) $di['incident'] = $da['incident'];

            return response()->json([
                'host_info'   => [
                    'status'     => 'UP',
                    'hostname'   => $host,
                    'os'         => strtolower(PHP_OS_FAMILY),
                    'platform'   => php_uname('r'),
                    'checked_at' => $this->now(),
                ],
                'cpu_info'    => $ci,
                'memory_info' => $mi,
                'disk_info'   => $di,
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
