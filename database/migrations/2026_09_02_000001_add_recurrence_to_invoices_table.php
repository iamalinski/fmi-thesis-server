<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Marks the invoice as a template the scheduler copies on a cycle
            $table->boolean('is_recurring')->default(false)->after('status');
            $table->enum('recurrence_type', ['weekly', 'monthly'])->nullable()->after('is_recurring');

            // 1 = Monday ... 7 = Sunday (ISO-8601), used for weekly recurrence
            $table->unsignedTinyInteger('recurrence_day_of_week')->nullable()->after('recurrence_type');

            // 1..31, used for monthly recurrence; clamped to the last day of
            // shorter months (e.g. 31 becomes 28/29 in February)
            $table->unsignedTinyInteger('recurrence_day_of_month')->nullable()->after('recurrence_day_of_week');

            // The next date the scheduler should emit a copy on
            $table->date('recurrence_next_run_at')->nullable()->after('recurrence_day_of_month');
            $table->timestamp('recurrence_last_run_at')->nullable()->after('recurrence_next_run_at');

            // Set on generated copies, pointing back at the template invoice
            $table->foreignId('recurrence_parent_id')->nullable()->after('recurrence_last_run_at')
                ->constrained('invoices')->nullOnDelete();

            $table->index(['is_recurring', 'recurrence_next_run_at'], 'invoices_recurrence_due_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_recurrence_due_index');
            $table->dropForeign(['recurrence_parent_id']);
            $table->dropColumn([
                'is_recurring',
                'recurrence_type',
                'recurrence_day_of_week',
                'recurrence_day_of_month',
                'recurrence_next_run_at',
                'recurrence_last_run_at',
                'recurrence_parent_id',
            ]);
        });
    }
};
