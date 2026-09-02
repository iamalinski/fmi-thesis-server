<?php

namespace App\Console\Commands;

use App\Services\RecurringInvoiceService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateRecurringInvoices extends Command
{
    protected $signature = 'invoices:generate-recurring
                            {--date= : Process the run as if today were this date (Y-m-d), for testing}';

    protected $description = 'Generate new invoices from every recurring invoice that is due today';

    public function handle(RecurringInvoiceService $service): int
    {
        $today = $this->option('date')
            ? CarbonImmutable::parse($this->option('date'))->startOfDay()
            : CarbonImmutable::now()->startOfDay();

        $generated = $service->generateDue($today);

        foreach ($generated as $invoice) {
            $this->line(sprintf(
                'Invoice %s generated from #%d',
                $invoice->invoice_number,
                $invoice->recurrence_parent_id
            ));
        }

        $this->info(count($generated) . ' recurring invoice(s) generated for ' . $today->toDateString() . '.');

        Log::info('Recurring invoices generated', [
            'date' => $today->toDateString(),
            'count' => count($generated),
        ]);

        return self::SUCCESS;
    }
}
