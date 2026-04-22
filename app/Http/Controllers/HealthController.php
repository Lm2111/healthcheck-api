<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;

class HealthController extends Controller
{
    private function now(): string
    {
        return Carbon::now()->format('D, d M Y H:i:s') . ' CET';
    }

    public function health(Request $request)
    {
        try {
            if ($request->query('force_error') === 'true') {
                throw new \Exception('Forced error for testing');
            }
            return response()->json([
                'status'     => 'UP',
                'hostname'   => gethostname(),
                'os'         => strtolower(PHP_OS_FAMILY),
                'platform'   => php_uname('r'),
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
