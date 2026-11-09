<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ticket_subcategories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['ticket_category_id', 'name']);
        });

        Schema::create('ticket_priorities', function (Blueprint $table) {
            $table->id();
            $table->string('name', 20)->unique();
            $table->unsignedSmallInteger('response_minutes');
            $table->unsignedInteger('resolution_minutes');
            $table->unsignedTinyInteger('sort_order')->unique();
            $table->timestamps();
        });

        Schema::create('ticket_number_counters', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number', 32)->unique();
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ticket_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('ticket_subcategory_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ticket_priority_id')->constrained()->restrictOnDelete();
            $table->string('status', 30)->default('Open')->index();
            $table->string('subject');
            $table->text('description');
            $table->string('building')->nullable();
            $table->string('room_number', 40)->nullable();
            $table->string('asset_number', 80)->nullable();
            $table->text('resolution_details')->nullable();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'ticket_priority_id']);
            $table->index(['department_id', 'created_at']);
            $table->index(['requester_id', 'created_at']);
        });

        Schema::create('ticket_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('technician_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->dateTime('assigned_at');
            $table->dateTime('ended_at')->nullable();
            $table->timestamps();
            $table->index(['technician_id', 'ended_at']);
        });

        Schema::create('ticket_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->text('body');
            $table->boolean('is_internal')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('ticket_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('ticket_comment_id')->nullable()->constrained('ticket_comments')->nullOnDelete();
            $table->string('disk', 40)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });

        Schema::create('ticket_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->index(['ticket_id', 'created_at']);
        });

        Schema::create('sla_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_priority_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('response_minutes');
            $table->unsignedInteger('resolution_minutes');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('ticket_sla_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->unique()->constrained()->cascadeOnDelete();
            $table->dateTime('response_due_at');
            $table->dateTime('resolution_due_at');
            $table->timestamp('response_met_at')->nullable();
            $table->timestamp('resolution_met_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->unsignedInteger('paused_minutes')->default(0);
            $table->timestamps();
        });

        Schema::create('asset_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_tag', 80)->unique();
            $table->string('serial_number', 100)->nullable()->unique();
            $table->string('name');
            $table->foreignId('asset_category_id')->constrained()->restrictOnDelete();
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('building')->nullable();
            $table->string('room_number', 40)->nullable();
            $table->date('acquired_on')->nullable();
            $table->date('warranty_expires_on')->nullable();
            $table->string('condition', 40)->default('Good');
            $table->string('status', 40)->default('In Service');
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ticket_assets', function (Blueprint $table) {
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->primary(['ticket_id', 'asset_id']);
        });

        Schema::create('maintenance_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('instructions')->nullable();
            $table->date('scheduled_for')->index();
            $table->string('status', 30)->default('Scheduled');
            $table->timestamps();
        });

        Schema::create('maintenance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->foreignId('maintenance_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('performed_at')->nullable();
            $table->text('findings')->nullable();
            $table->text('actions_performed')->nullable();
            $table->date('next_maintenance_on')->nullable();
            $table->string('status', 30)->default('Open');
            $table->timestamps();
        });

        Schema::create('knowledge_base_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('category')->index();
            $table->longText('body');
            $table->boolean('is_published')->default(false)->index();
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comments')->nullable();
            $table->timestamps();
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('name');
            $table->boolean('is_working_day')->default(false);
            $table->timestamps();
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 100)->index();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('feedback');
        Schema::dropIfExists('knowledge_base_articles');
        Schema::dropIfExists('maintenance_records');
        Schema::dropIfExists('maintenance_schedules');
        Schema::dropIfExists('ticket_assets');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('asset_categories');
        Schema::dropIfExists('ticket_sla_records');
        Schema::dropIfExists('sla_policies');
        Schema::dropIfExists('ticket_status_history');
        Schema::dropIfExists('ticket_attachments');
        Schema::dropIfExists('ticket_comments');
        Schema::dropIfExists('ticket_assignments');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('ticket_number_counters');
        Schema::dropIfExists('ticket_priorities');
        Schema::dropIfExists('ticket_subcategories');
        Schema::dropIfExists('ticket_categories');
    }
};
