<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->boolean('is_set')->default(false)->after('is_listable');
        });

        Schema::create('item_set_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('set_item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->unique(['set_item_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_set_components');

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('is_set');
        });
    }
};
