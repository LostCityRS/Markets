<?php

use Database\Seeders\ItemSetsSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        (new ItemSetsSeeder)->run();
    }

    public function down(): void
    {
        // Leaving down() empty: sets are data, not schema. Rolling back the
        // is_set column migration drops the pivot and column — that already
        // removes every trace of the sets from the DB.
    }
};
