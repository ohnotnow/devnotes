<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Sqlite cannot create full-text indexes, so this only runs on drivers
     * that can (the test suite uses in-memory sqlite with Scout's collection
     * engine, which does not need the index).
     */
    public function up(): void
    {
        if (! $this->supportsFullText()) {
            return;
        }

        Schema::table('notes', function (Blueprint $table) {
            $table->fullText(['title', 'body']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! $this->supportsFullText()) {
            return;
        }

        Schema::table('notes', function (Blueprint $table) {
            $table->dropFullText(['title', 'body']);
        });
    }

    private function supportsFullText(): bool
    {
        return in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb', 'pgsql']);
    }
};
