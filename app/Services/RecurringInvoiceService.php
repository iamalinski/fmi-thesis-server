<?php

namespace App\Services;

use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class RecurringInvoiceService
{
    public function __construct(private InvoicePdfService $pdfService)
    {
    }

    /**
     * Generate copies of every recurring invoice whose next run date has
     * arrived. Returns the invoices that were created.
     *
     * @return array<int, Invoice>
     */
    public function generateDue(?CarbonImmutable $today = null): array
    {
        $today = ($today ?? CarbonImmutable::now())->startOfDay();

        $generated = [];

        Invoice::query()
            ->dueForRecurrence($today)
            ->with('items')
            ->orderBy('id')
            ->each(function (Invoice $template) use ($today, &$generated) {
                $copy = $this->generateFrom($template, $today);

                if ($copy) {
                    $generated[] = $copy;
                }
            });

        return $generated;
    }

    /**
     * Copy a recurring invoice into a brand new invoice dated $date and move
     * the template on to its next run date. Returns null when the template
     * has already been processed for that date.
     */
    public function generateFrom(Invoice $template, ?CarbonImmutable $date = null): ?Invoice
    {
        $date = ($date ?? CarbonImmutable::now())->startOfDay();

        $copy = DB::transaction(function () use ($template, $date) {
            // Re-read under a row lock so two overlapping scheduler runs
            // cannot emit the same invoice twice.
            $template = Invoice::query()->lockForUpdate()->find($template->getKey());

            if (! $template || ! $template->is_recurring || ! $template->recurrence_next_run_at) {
                return null;
            }

            if ($template->recurrence_next_run_at->startOfDay()->greaterThan($date)) {
                return null;
            }

            $copy = $this->replicate($template, $date);

            $template->forceFill([
                'recurrence_last_run_at' => $date,
                'recurrence_next_run_at' => $template->nextRecurrenceDate($date),
            ])->save();

            return $copy;
        });

        if ($copy) {
            $this->pdfService->generateAndStore($copy);
        }

        return $copy;
    }

    /**
     * Duplicate the invoice and its line items as a fresh, unpaid invoice.
     */
    private function replicate(Invoice $template, CarbonImmutable $date): Invoice
    {
        // Keep the original payment window (e.g. "due 14 days after issue")
        $termInDays = (int) $template->date->diffInDays($template->due_date);

        $copy = Invoice::query()->create([
            'user_id' => $template->user_id,
            'client_id' => $template->client_id,
            'invoice_number' => Invoice::nextInvoiceNumber(),
            'date' => $date->toDateString(),
            'due_date' => $date->addDays($termInDays)->toDateString(),
            'subtotal' => $template->subtotal,
            'vat' => $template->vat,
            'amount' => $template->amount,
            'payment_method' => $template->payment_method,
            'deal_location' => $template->deal_location,
            'author' => $template->author,
            'status' => 'unpaid',
            'notes' => $template->notes,
            // The copy is a plain invoice: only the template keeps repeating
            'is_recurring' => false,
            'recurrence_parent_id' => $template->getKey(),
        ]);

        foreach ($template->items as $item) {
            $copy->items()->create([
                'article_id' => $item->article_id,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'discount' => $item->discount,
                'total' => $item->total,
            ]);
        }

        return $copy;
    }
}
