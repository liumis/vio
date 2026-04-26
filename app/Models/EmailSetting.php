<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailSetting extends Model
{
    protected $fillable = [
        'from_name',
        'from_email',
        'reply_to',
        'subject',
    ];
}
