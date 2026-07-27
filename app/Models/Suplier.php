<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'phone', 'city', 'province', 'adress', 'postal_code'])]
class Suplier extends Model
{
    use HasFactory;
}
