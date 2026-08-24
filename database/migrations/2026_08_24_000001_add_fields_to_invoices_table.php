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
            $table->decimal('subtotal', 10, 2)->default(0)->after('amount');
            $table->decimal('vat', 10, 2)->default(0)->after('subtotal');
            $table->string('payment_method')->nullable()->after('vat');
            $table->string('deal_location')->nullable()->after('payment_method');
            $table->string('author')->nullable()->after('deal_location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['subtotal', 'vat', 'payment_method', 'deal_location', 'author']);
        });
    }
};
