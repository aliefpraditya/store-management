<?php

namespace App\Enums;

enum ProvisioningResult: string
{
    case Ok = 'ok';
    case Failed = 'failed';
}
