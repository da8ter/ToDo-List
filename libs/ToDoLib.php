<?php

declare(strict_types=1);

if (!function_exists('TDL_CreateList')) {
    function TDL_CreateList(int $ManagerInstanceID, string $Name): int
    {
        return TDLM_CreateList($ManagerInstanceID, $Name);
    }
}
