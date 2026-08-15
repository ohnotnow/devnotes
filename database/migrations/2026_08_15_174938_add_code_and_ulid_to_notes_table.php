<?php

use App\Models\Note;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Columns land nullable, existing rows are backfilled, then the unique
     * indexes go on. They stay nullable at the DB level - the model's
     * creating hook is the guarantee for new rows.
     */
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->string('ulid', 26)->nullable();
            $table->string('code', 5)->nullable();
        });

        Note::withTrashed()->each(function (Note $note) {
            $note->timestamps = false;
            $note->forceFill([
                'ulid' => (string) Str::ulid(),
                'code' => Note::mintCode(),
            ])->saveQuietly();
        });

        Schema::table('notes', function (Blueprint $table) {
            $table->unique('ulid');
            $table->unique('code');
        });
    }

    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropUnique(['ulid']);
            $table->dropUnique(['code']);
            $table->dropColumn(['ulid', 'code']);
        });
    }
};
