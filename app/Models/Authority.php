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
        'email_pattern',
        'mail_template',
        'informavimo_terminas',
        'informavimo_terminas_tipas',
        'info_pdf_template',
    ];

    protected function casts(): array
    {
        return [
            'informavimo_terminas' => 'integer',
        ];
    }
}
