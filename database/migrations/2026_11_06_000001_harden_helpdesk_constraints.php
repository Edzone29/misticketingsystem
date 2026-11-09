<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->unique('maintenance_schedule_id', 'maintenance_records_schedule_unique');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->string('ticket_number', 32)->change();
        });

        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            DB::unprepared("CREATE TRIGGER feedback_rating_insert_check BEFORE INSERT ON feedback WHEN NEW.rating < 1 OR NEW.rating > 5 BEGIN SELECT RAISE(ABORT, 'feedback rating must be between 1 and 5'); END");
            DB::unprepared("CREATE TRIGGER feedback_rating_update_check BEFORE UPDATE OF rating ON feedback WHEN NEW.rating < 1 OR NEW.rating > 5 BEGIN SELECT RAISE(ABORT, 'feedback rating must be between 1 and 5'); END");
        } else {
            DB::statement('ALTER TABLE feedback ADD CONSTRAINT feedback_rating_check CHECK (rating BETWEEN 1 AND 5)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS feedback_rating_insert_check');
            DB::unprepared('DROP TRIGGER IF EXISTS feedback_rating_update_check');
        } else {
            DB::statement('ALTER TABLE feedback DROP CONSTRAINT feedback_rating_check');
        }

        Schema::table('tickets', function (Blueprint $table) {
            $table->string('ticket_number', 20)->change();
        });

        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->dropUnique('maintenance_records_schedule_unique');
        });
    }
};
