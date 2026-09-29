<?php

namespace Tests;

use Illuminate\Database\Schema\Grammars\SQLiteGrammar;

/**
 * The pinned framework's SQLite grammar predates the
 * unsignedSmallInteger / unsignedTinyInteger column types used by newer
 * project migrations (production runs MySQL, which supports them).
 * SQLite ignores unsigned-ness entirely, so map the missing compilers to
 * their signed equivalents. Test environment only — never loaded in
 * production code paths.
 */
final class SqliteCompat
{
    private static bool $booted = false;

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }

        self::$booted = true;

        if (! SQLiteGrammar::hasMacro('typeUnsignedSmallInteger')) {
            SQLiteGrammar::macro('typeUnsignedSmallInteger', fn ($column) => $this->typeSmallInteger($column));
        }

        if (! SQLiteGrammar::hasMacro('typeUnsignedTinyInteger')) {
            SQLiteGrammar::macro('typeUnsignedTinyInteger', fn ($column) => $this->typeTinyInteger($column));
        }

        if (! SQLiteGrammar::hasMacro('typeUnsignedMediumInteger')) {
            SQLiteGrammar::macro('typeUnsignedMediumInteger', fn ($column) => $this->typeMediumInteger($column));
        }
    }
}
