<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_subcategories', function (Blueprint $table) {
            $table->unique(['id', 'ticket_category_id'], 'ticket_subcategory_category_pair_unique');
        });
        Schema::table('tickets', function (Blueprint $table) {
            $table->unique(['id', 'requester_id'], 'tickets_requester_pair_unique');
        });
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreign(['ticket_subcategory_id', 'ticket_category_id'], 'tickets_subcategory_category_fk')
                ->references(['id', 'ticket_category_id'])->on('ticket_subcategories')->restrictOnDelete();
        });
        Schema::table('feedback', function (Blueprint $table) {
            $table->foreign(['ticket_id', 'user_id'], 'feedback_ticket_requester_fk')
                ->references(['id', 'requester_id'])->on('tickets')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('feedback', function (Blueprint $table) {
            $table->dropForeign('feedback_ticket_requester_fk');
        });
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign('tickets_subcategory_category_fk');
            $table->dropUnique('tickets_requester_pair_unique');
        });
        Schema::table('ticket_subcategories', function (Blueprint $table) {
            $table->dropUnique('ticket_subcategory_category_pair_unique');
        });
    }
};
