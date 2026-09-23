<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->foreignId('lesson_time_id')->constrained()->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('next_group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 120);
            $table->enum('schedule', ['odd', 'even', 'daily']);
            $table->unsignedBigInteger('price');
            $table->unsignedBigInteger('early_discount')->default(0);
            $table->unsignedSmallInteger('lesson_count');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->unsignedBigInteger('teacher_rate')->default(0);
            $table->unsignedBigInteger('teacher_bonus_rate')->default(0);
            $table->timestamps();

            $table->index(['branch_id', 'ends_on']);
        });

        Schema::create('group_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->foreignId('lesson_time_id')->constrained()->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            $table->date('date');

            $table->unique(['group_id', 'date']);
            // Bir xona va bir o'qituvchi bir vaqtda ikki joyda bo'la olmaydi
            $table->unique(['room_id', 'lesson_time_id', 'date']);
            $table->unique(['teacher_id', 'lesson_time_id', 'date']);
            $table->index('date');
        });

        Schema::create('group_students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('add_note')->nullable();
            $table->foreignId('removed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remove_note')->nullable();
            $table->unsignedBigInteger('fine')->default(0);
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            $table->index(['group_id', 'is_active']);
            $table->index(['student_id', 'is_active']);
        });

        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('taken_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['group_id', 'date']);
            $table->index(['branch_id', 'date']);
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('attendance_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            $table->boolean('is_present')->default(false);
            $table->timestamps();

            $table->unique(['group_id', 'student_id', 'date']);
            $table->index(['student_id', 'date']);
            $table->index(['branch_id', 'date']);
        });

        Schema::create('balance_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 30);
            $table->bigInteger('amount');
            $table->bigInteger('balance_after');
            $table->foreignId('group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['student_id', 'id']);
            $table->index(['branch_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['balance_transactions', 'attendances', 'attendance_sessions', 'group_students', 'group_days', 'groups'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
