<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translation_values', function (Blueprint $table) {
            $table->id();
            $table->string('key', 191);   // e.g. "frontend.nav.home"
            $table->string('locale', 10); // e.g. "ar", "en"
            $table->text('value');        // translated string
            $table->timestamps();

            $table->unique(['key', 'locale']); // one value per key/locale pair
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_values');
    }
};
