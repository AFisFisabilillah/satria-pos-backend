<?php

namespace App;

enum VoucherType: string
{
    case FIXED = 'fixed';
    case PERCENT = 'percent';
}
