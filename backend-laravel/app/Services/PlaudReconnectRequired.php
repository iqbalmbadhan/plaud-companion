<?php

namespace App\Services;

use RuntimeException;

class PlaudReconnectRequired extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Plaud sign-in expired. Please connect again.');
    }
}
