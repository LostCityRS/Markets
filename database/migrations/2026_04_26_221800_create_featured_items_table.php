<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('featured_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained('listings')->cascadeOnDelete();
            $table->timestamp('featured_at');
            $table->date('featured_date');
            $table->index('featured_at');
            $table->unique('featured_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('featured_items');
    }
};
