<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'cin',
        'first_name',
        'last_name',
        'exam',
        'exam_date',
        'qr_hash',
        'qr_path',
        'grade',
    ];
}

