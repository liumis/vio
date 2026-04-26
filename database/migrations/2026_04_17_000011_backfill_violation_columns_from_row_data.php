<?php

use App\Models\Violation;
use App\Support\ViolationImportMapping;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Violation::query()->orderBy('id')->chunkById(100, function ($violations): void {
            foreach ($violations as $violation) {
                $rowData = $violation->row_data;
                if (! is_array($rowData) || $rowData === []) {
                    continue;
                }

                $attrs = ViolationImportMapping::mapRowToAttributes($rowData);
                if ($attrs === []) {
                    continue;
                }

                $violation->forceFill($attrs);
                $violation->saveQuietly();
            }
        });
    }

    public function down(): void
    {
        //
    }
};
