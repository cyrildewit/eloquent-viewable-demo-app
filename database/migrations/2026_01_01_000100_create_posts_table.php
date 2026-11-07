<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('topic');
            $table->text('excerpt');
            $table->longText('body');
            $table->unsignedSmallInteger('hue');
            $table->unsignedSmallInteger('reading_minutes');
            $table->timestamp('published_at')->nullable()->index();

            // Counter columns from `querying.counters`, written by views:recount.
            $table->unsignedBigInteger('total_views')->default(0)->index();
            $table->unsignedBigInteger('unique_viewers')->default(0);
            $table->unsignedBigInteger('views_last_week')->default(0)->index();
            $table->unsignedBigInteger('google_views_30d')->default(0);
            $table->double('hot_score')->default(0)->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
