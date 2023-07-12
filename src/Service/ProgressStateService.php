<?php
// src/Service/ProgressStateService.php

namespace App\Service;

class ProgressStateService
{
    private $progress;

    public function __construct()
    {
        $this->progress = 0;
    }

    public function getValue(): int
    {
        return $this->progress;
    }

    public function setValue(int $value): void
    {
        $this->progress = $value;
    }
}
