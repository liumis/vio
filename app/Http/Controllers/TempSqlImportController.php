<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TempSqlImportController extends Controller
{
    public function show()
    {
        return view('temp-import-sql');
    }

    public function import(Request $request)
    {
        $request->validate([
            'token' => ['required', 'string'],
            'sql_file' => ['required', 'file', 'mimes:sql,txt', 'max:102400'],
        ]);

        $expectedToken = (string) env('TEMP_IMPORT_TOKEN', '');
        if ($expectedToken === '' || ! hash_equals($expectedToken, (string) $request->input('token'))) {
            throw ValidationException::withMessages([
                'token' => 'Invalid import token.',
            ]);
        }

        $sql = file_get_contents($request->file('sql_file')->getRealPath());
        if ($sql === false || trim($sql) === '') {
            throw ValidationException::withMessages([
                'sql_file' => 'SQL file is empty or unreadable.',
            ]);
        }

        $statements = $this->splitSqlStatements($sql);
        if ($statements === []) {
            throw ValidationException::withMessages([
                'sql_file' => 'No executable SQL statements were found.',
            ]);
        }

        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if ($driver === 'mysql') {
            $connection->statement('SET FOREIGN_KEY_CHECKS=0');
        } elseif ($driver === 'sqlite') {
            $connection->statement('PRAGMA foreign_keys = OFF');
        }

        try {
            foreach ($statements as $statement) {
                $connection->unprepared($statement);
            }
        } finally {
            if ($driver === 'mysql') {
                $connection->statement('SET FOREIGN_KEY_CHECKS=1');
            } elseif ($driver === 'sqlite') {
                $connection->statement('PRAGMA foreign_keys = ON');
            }
        }

        return back()->with('status', 'Import completed successfully. Executed '.count($statements).' SQL statements.');
    }

    /**
     * Split SQL dump into executable statements.
     *
     * This supports standard mysqldump output (without stored procedures).
     *
     * @return array<int, string>
     */
    private function splitSqlStatements(string $sql): array
    {
        $sql = preg_replace('/\/\*![0-9]{5}.*?\*\//s', '', $sql) ?? $sql;
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        $sql = preg_replace('/^\s*#.*$/m', '', $sql) ?? $sql;

        $statements = [];
        $buffer = '';
        $inSingleQuote = false;
        $inDoubleQuote = false;
        $escaped = false;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $buffer .= $char;

            if ($escaped) {
                $escaped = false;
                continue;
            }

            if ($char === '\\') {
                $escaped = true;
                continue;
            }

            if ($char === "'" && ! $inDoubleQuote) {
                $inSingleQuote = ! $inSingleQuote;
                continue;
            }

            if ($char === '"' && ! $inSingleQuote) {
                $inDoubleQuote = ! $inDoubleQuote;
                continue;
            }

            if ($char === ';' && ! $inSingleQuote && ! $inDoubleQuote) {
                $statement = trim($buffer);
                if ($statement !== ';' && $statement !== '') {
                    $statements[] = rtrim($statement, ';');
                }
                $buffer = '';
            }
        }

        $tail = trim($buffer);
        if ($tail !== '') {
            $statements[] = rtrim($tail, ';');
        }

        return $statements;
    }
}
