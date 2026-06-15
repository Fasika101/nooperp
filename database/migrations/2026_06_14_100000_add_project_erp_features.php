<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- project_tasks: progress, estimated_hours, is_starred ---
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->unsignedTinyInteger('progress')->default(0)->after('due_date');
            $table->decimal('estimated_hours', 6, 2)->nullable()->after('progress');
            $table->boolean('is_starred')->default(false)->after('estimated_hours');
        });

        // --- projects: budget ---
        Schema::table('projects', function (Blueprint $table) {
            $table->decimal('budget', 12, 2)->nullable()->after('end_date');
        });

        // --- expenses: project_id ---
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('description')
                ->constrained('projects')->nullOnDelete();
        });

        // --- project_labels ---
        Schema::create('project_labels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('color', 20)->default('gray'); // tailwind color name: blue, green, red, yellow, purple, etc.
            $table->timestamps();
        });

        // --- project_label_task pivot ---
        Schema::create('project_label_task', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_label_id')->constrained('project_labels')->cascadeOnDelete();
            $table->foreignId('project_task_id')->constrained('project_tasks')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['project_label_id', 'project_task_id']);
        });

        // --- project_task_comments ---
        Schema::create('project_task_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_task_id')->constrained('project_tasks')->cascadeOnDelete();
            $table->text('body');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // --- project_task_checklists ---
        Schema::create('project_task_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_task_id')->constrained('project_tasks')->cascadeOnDelete();
            $table->string('title');
            $table->boolean('is_done')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        // --- project_time_logs ---
        Schema::create('project_time_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_task_id')->constrained('project_tasks')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('hours', 6, 2);
            $table->date('logged_on');
            $table->string('note', 512)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_time_logs');
        Schema::dropIfExists('project_task_checklists');
        Schema::dropIfExists('project_task_comments');
        Schema::dropIfExists('project_label_task');
        Schema::dropIfExists('project_labels');

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropColumn('project_id');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('budget');
        });

        Schema::table('project_tasks', function (Blueprint $table) {
            $table->dropColumn(['progress', 'estimated_hours', 'is_starred']);
        });
    }
};
