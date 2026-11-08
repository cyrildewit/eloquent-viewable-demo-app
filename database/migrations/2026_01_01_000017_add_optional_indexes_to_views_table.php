<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The three optional indexes from the README's "Database indexes" section.
 * views:doctor recommends them for this config: unique() and returning()
 * read the visitor, rising(), anomalies() and views:detect-spikes count a
 * whole type per window, and alsoViewed() and recommended() walk each
 * visitor's history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('views', function (Blueprint $table) {
            $table->index(['viewable_type', 'viewable_id', 'viewed_at', 'visitor'], 'views_viewable_viewed_at_visitor_index');
            $table->index(['viewable_type', 'viewed_at'], 'views_viewable_type_viewed_at_index');
            $table->index(['visitor', 'viewed_at', 'viewable_type', 'viewable_id'], 'views_visitor_history_index');
        });
    }

    public function down(): void
    {
        Schema::table('views', function (Blueprint $table) {
            $table->dropIndex('views_viewable_viewed_at_visitor_index');
            $table->dropIndex('views_viewable_type_viewed_at_index');
            $table->dropIndex('views_visitor_history_index');
        });
    }
};
