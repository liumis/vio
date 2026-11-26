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
        'requires_contract',
    ];

    protected function casts(): array
    {
        return [
            'informavimo_terminas' => 'integer',
            'requires_contract' => 'boolean',
        ];
    }

    /**
     * Julianus (and similar): reply must include / track the rental contract (RNT).
     */
    public function requiresContract(): bool
    {
        if ((bool) $this->requires_contract) {
            return true;
        }

        $hay = strtolower(trim((string) ($this->email_pattern ?? '')).' '.trim((string) ($this->name ?? '')));

        return str_contains($hay, 'julianus');
    }
}
