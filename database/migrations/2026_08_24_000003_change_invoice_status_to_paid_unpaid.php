<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Widen to a plain string so existing enum values can be remapped
        DB::statement("ALTER TABLE invoices MODIFY COLUMN status VARCHAR(20) NOT NULL DEFAULT 'unpaid'");

        // Map legacy statuses onto the new two-state model
        DB::table('invoices')->whereIn('status', ['pending', 'overdue'])->update(['status' => 'unpaid']);

        // Lock down to the final enum
        DB::statement("ALTER TABLE invoices MODIFY COLUMN status ENUM('paid', 'unpaid') NOT NULL DEFAULT 'unpaid'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE invoices MODIFY COLUMN status VARCHAR(20) NOT NULL DEFAULT 'pending'");

        DB::table('invoices')->where('status', 'unpaid')->update(['status' => 'pending']);

        DB::statement("ALTER TABLE invoices MODIFY COLUMN status ENUM('paid', 'pending', 'overdue') NOT NULL DEFAULT 'pending'");
    }
};
