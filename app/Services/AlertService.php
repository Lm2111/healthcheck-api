<?php

namespace App\Services;

class AlertService
{
    public function checkAndAlert($metric, $value, $hostname): array
    {
        return ['triggered' => false, 'incident' => null];
    }
}