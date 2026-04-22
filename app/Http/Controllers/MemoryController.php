<?php
 
namespace App\Http\Controllers;
 
use Illuminate\Http\Request;

use Carbon\Carbon;

use App\Services\AlertService;
 
class MemoryController extends Controller

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
 
    private function getMemoryInfo(): array

    {

        $totalBytes = intval(shell_exec('wmic ComputerSystem get TotalPhysicalMemory /Value | findstr ='));

        preg_match('/FreePhysicalMemory=(\d+)/', shell_exec('wmic OS get FreePhysicalMemory /Value') ?? '', $m);

        $free = intval($m[1] ?? 0) * 1024;

        $used = $totalBytes - $free;

        $gb   = 1024 ** 3;

        return [

            'total_gb'     => round($totalBytes / $gb, 2),

            'used_gb'      => round($used  / $gb, 2),

            'free_gb'      => round($free  / $gb, 2),

            'used_percent' => round(($used / $totalBytes) * 100, 2),

        ];

    }
 
    public function memory(Request $request)

    {

        try {

            if ($request->query('force_error') === 'true') {

                throw new \Exception('Unable to read memory metrics');

            }

            $mem   = $this->getMemoryInfo();

            $alert = $this->alertService->checkAndAlert('RAM', $mem['used_percent'], gethostname());

            $data  = array_merge($mem, [

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
 
