<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('episodes', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('number');
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('topic');
            $table->text('summary');
            $table->string('guest')->nullable();
            $table->unsignedSmallInteger('hue');
            $table->unsignedSmallInteger('duration_minutes');
            $table->timestamp('published_at')->nullable()->index();

            $table->unsignedBigInteger('total_views')->default(0);
            $table->unsignedBigInteger('plays')->default(0)->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('episodes');
    }
};
