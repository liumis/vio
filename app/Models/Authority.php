<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Authority extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'from_email',
        'main_email',
        'to_email',
        'mail_template',
    ];
}
