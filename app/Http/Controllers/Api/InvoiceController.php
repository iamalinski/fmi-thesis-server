<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\InvoiceMail;
use App\Models\Invoice;
use App\Services\InvoicePdfService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class InvoiceController extends Controller
{
    public function __construct(private InvoicePdfService $pdfService)
    {
    }

    public function index(Request $request)
    {
        $invoices = $request->user()->invoices()
            ->with('client')
            ->when($request->filled('search'), function ($query) use ($request) {
                return $query->where('invoice_number', 'like', '%' . $request->search . '%');
            })
            ->when($request->filled('status') && $request->status !== 'all', function ($query) use ($request) {
                return $query->where('status', $request->status);
            })
            ->when($request->filled('client'), function ($query) use ($request) {
                return $query->whereHas('client', function ($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->client . '%');
                });
            })
            // `recurring=1` narrows the list down to the repeating invoices
            ->when($request->filled('recurring'), function ($query) use ($request) {
                return $query->where('is_recurring', $request->boolean('recurring'));
            })
            ->orderBy('date', 'desc')
            ->paginate($request->per_page ?? 10);

        return response()->json($invoices);
    }

    public function store(Request $request)
    {
        $data = $this->validateInvoice($request);

        $invoice = DB::transaction(function () use ($request, $data) {
            $clientId = $this->resolveClientId($request, $data);
            $totals = $this->calculateTotals($data['items']);

            $invoice = $request->user()->invoices()->create(array_merge([
                'client_id' => $clientId,
                'invoice_number' => Invoice::nextInvoiceNumber(),
                'date' => $data['date'],
                'due_date' => $data['due_date'],
                'subtotal' => $totals['subtotal'],
                'vat' => $totals['vat'],
                'amount' => $totals['amount'],
                'payment_method' => $data['payment_method'] ?? null,
                'deal_location' => $data['deal_location'] ?? null,
                'author' => $data['author'] ?? null,
                'status' => $data['status'] ?? 'unpaid',
                'notes' => $data['notes'] ?? null,
            ], $this->recurrenceAttributes($request, $data)));

            $this->syncItems($invoice, $data['items']);

            return $invoice;
        });

        $this->pdfService->generateAndStore($invoice);

        return response()->json([
            'message' => 'Invoice created successfully',
            'invoice' => $invoice->load('client', 'items.article'),
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $invoice = $request->user()->invoices()
            ->with('client', 'items.article')
            ->findOrFail($id);

        return response()->json($invoice);
    }

    public function update(Request $request, $id)
    {
        $invoice = $request->user()->invoices()->findOrFail($id);

        $data = $this->validateInvoice($request);

        DB::transaction(function () use ($request, $invoice, $data) {
            $clientId = $this->resolveClientId($request, $data);
            $totals = $this->calculateTotals($data['items']);

            $invoice->update(array_merge([
                'client_id' => $clientId,
                'date' => $data['date'],
                'due_date' => $data['due_date'],
                'subtotal' => $totals['subtotal'],
                'vat' => $totals['vat'],
                'amount' => $totals['amount'],
                'payment_method' => $data['payment_method'] ?? null,
                'deal_location' => $data['deal_location'] ?? null,
                'author' => $data['author'] ?? null,
                'status' => $data['status'] ?? $invoice->status,
                'notes' => $data['notes'] ?? null,
            ], $this->recurrenceAttributes($request, $data, $invoice)));

            // Replace line items with the submitted set
            $invoice->items()->delete();
            $this->syncItems($invoice, $data['items']);
        });

        // Regenerate the printable PDF to reflect the edited invoice
        $this->pdfService->generateAndStore($invoice->fresh());

        return response()->json([
            'message' => 'Invoice updated successfully',
            'invoice' => $invoice->fresh()->load('client', 'items.article'),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $invoice = $request->user()->invoices()->findOrFail($id);

        $this->pdfService->delete($invoice);
        $invoice->delete();

        return response()->json([
            'message' => 'Invoice deleted successfully',
        ]);
    }

    /**
     * Toggle an invoice between paid and unpaid from the list view.
     */
    public function updateStatus(Request $request, $id)
    {
        $invoice = $request->user()->invoices()->findOrFail($id);

        $data = $request->validate([
            'status' => ['required', Rule::in(['paid', 'unpaid'])],
        ]);

        $invoice->update(['status' => $data['status']]);

        return response()->json([
            'message' => 'Invoice status updated successfully',
            'invoice' => $invoice->fresh()->load('client'),
        ]);
    }

    /**
     * Turn repetition on, or change its schedule, without touching the rest
     * of the invoice.
     */
    public function updateRecurrence(Request $request, $id)
    {
        $invoice = $request->user()->invoices()->findOrFail($id);

        $data = $request->validate($this->recurrenceRules($request, true));

        $invoice->update($this->recurrenceAttributes($request, $data, $invoice));

        return response()->json([
            'message' => 'Invoice recurrence updated successfully',
            'invoice' => $invoice->fresh()->load('client'),
        ]);
    }

    /**
     * Stop an invoice from repeating. Copies already generated are kept.
     */
    public function destroyRecurrence(Request $request, $id)
    {
        $invoice = $request->user()->invoices()->findOrFail($id);

        $invoice->update([
            'is_recurring' => false,
            'recurrence_type' => null,
            'recurrence_day_of_week' => null,
            'recurrence_day_of_month' => null,
            'recurrence_next_run_at' => null,
        ]);

        return response()->json([
            'message' => 'Invoice recurrence cancelled successfully',
            'invoice' => $invoice->fresh()->load('client'),
        ]);
    }

    /**
     * Stream the generated PDF for download.
     */
    public function download(Request $request, $id)
    {
        $invoice = $request->user()->invoices()
            ->with('client', 'items', 'user.company')
            ->findOrFail($id);

        return response($this->pdfService->getContents($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $this->pdfService->downloadName($invoice) . '"',
        ]);
    }

    /**
     * Email the invoice, with its PDF attached, to the address entered by the
     * user. The message is sent through the configured mailer (Mailgun).
     */
    public function send(Request $request, $id)
    {
        $invoice = $request->user()->invoices()
            ->with('client', 'items', 'user.company')
            ->findOrFail($id);

        $data = $request->validate([
            'email' => 'required|email|max:255',
            'note' => 'nullable|string|max:1000',
        ]);

        try {
            Mail::to($data['email'])->send(new InvoiceMail($invoice, $data['note'] ?? null));
        } catch (\Throwable $e) {
            Log::error('Sending invoice ' . $invoice->invoice_number . ' to ' . $data['email'] . ' failed: ' . $e->getMessage());

            return response()->json([
                'message' => 'The invoice could not be sent. Please try again later.',
            ], 502);
        }

        return response()->json([
            'message' => 'Invoice sent successfully',
            'email' => $data['email'],
        ]);
    }

    /**
     * Validate the invoice payload. A buyer is provided either as an existing
     * client_id, or as an inline `client` object that will be created.
     */
    private function validateInvoice(Request $request): array
    {
        return $request->validate(array_merge([
            'client_id' => 'nullable|exists:clients,id',
            'client' => 'required_without:client_id|array',
            'client.name' => 'required_without:client_id|string|max:255',
            'client.number' => 'required_without:client_id|string|max:20',
            'client.vat_number' => 'nullable|string|max:20',
            'client.acc_person' => 'required_without:client_id|string|max:255',
            'client.address' => 'required_without:client_id|string|max:255',

            'date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:date',
            'payment_method' => 'nullable|string|max:255',
            'deal_location' => 'nullable|string|max:255',
            'author' => 'nullable|string|max:255',
            'status' => ['nullable', Rule::in(['paid', 'unpaid'])],
            'notes' => 'nullable|string',

            'items' => 'required|array|min:1',
            'items.*.article_id' => 'nullable|exists:articles,id',
            'items.*.description' => 'required|string|max:255',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0|max:100',
        ], $this->recurrenceRules($request)));
    }

    /**
     * Rules for the repeat settings. A weekly repeat requires the weekday, a
     * monthly one the day of the month; both are ignored when the invoice
     * does not repeat. $required makes `is_recurring` itself mandatory, as on
     * the dedicated recurrence endpoint.
     */
    private function recurrenceRules(Request $request, bool $required = false): array
    {
        $repeats = fn () => $request->boolean('is_recurring');

        $repeatsWith = fn (string $type) => fn () => $request->boolean('is_recurring')
            && $request->input('recurrence_type') === $type;

        return [
            'is_recurring' => [$required ? 'required' : 'nullable', 'boolean'],
            'recurrence_type' => [
                Rule::requiredIf($repeats),
                'nullable',
                Rule::in([Invoice::RECURRENCE_WEEKLY, Invoice::RECURRENCE_MONTHLY]),
            ],
            // 1 = Monday ... 7 = Sunday
            'recurrence_day_of_week' => [
                Rule::requiredIf($repeatsWith(Invoice::RECURRENCE_WEEKLY)),
                'nullable', 'integer', 'min:1', 'max:7',
            ],
            // 1..31, clamped to the last day of shorter months
            'recurrence_day_of_month' => [
                Rule::requiredIf($repeatsWith(Invoice::RECURRENCE_MONTHLY)),
                'nullable', 'integer', 'min:1', 'max:31',
            ],
        ];
    }

    /**
     * Translate the validated repeat settings into invoice columns, including
     * the date the scheduler should next fire on.
     */
    private function recurrenceAttributes(Request $request, array $data, ?Invoice $existing = null): array
    {
        if (! $request->boolean('is_recurring')) {
            return [
                'is_recurring' => false,
                'recurrence_type' => null,
                'recurrence_day_of_week' => null,
                'recurrence_day_of_month' => null,
                'recurrence_next_run_at' => null,
            ];
        }

        $weekly = $data['recurrence_type'] === Invoice::RECURRENCE_WEEKLY;

        $attributes = [
            'is_recurring' => true,
            'recurrence_type' => $data['recurrence_type'],
            'recurrence_day_of_week' => $weekly ? (int) $data['recurrence_day_of_week'] : null,
            'recurrence_day_of_month' => $weekly ? null : (int) $data['recurrence_day_of_month'],
        ];

        $attributes['recurrence_next_run_at'] = $this->resolveNextRunAt($attributes, $existing);

        return $attributes;
    }

    /**
     * An unchanged schedule keeps its pending run date, so merely editing an
     * invoice never postpones the copy that is already queued up.
     */
    private function resolveNextRunAt(array $attributes, ?Invoice $existing)
    {
        $scheduleUnchanged = $existing
            && $existing->is_recurring
            && $existing->recurrence_type === $attributes['recurrence_type']
            && $existing->recurrence_day_of_week === $attributes['recurrence_day_of_week']
            && $existing->recurrence_day_of_month === $attributes['recurrence_day_of_month'];

        if ($scheduleUnchanged && $existing->recurrence_next_run_at) {
            return $existing->recurrence_next_run_at;
        }

        // A throwaway model carrying the new settings computes the first run
        return (new Invoice($attributes))->nextRecurrenceDate(CarbonImmutable::now());
    }

    /**
     * Return the id of the buyer, creating a new client for the user when the
     * invoice carries an inline client object instead of an existing id.
     */
    private function resolveClientId(Request $request, array $data): int
    {
        if (! empty($data['client_id'])) {
            return (int) $data['client_id'];
        }

        $client = $request->user()->clients()->create([
            'name' => $data['client']['name'],
            'number' => $data['client']['number'],
            'vat_number' => $data['client']['vat_number'] ?? null,
            'acc_person' => $data['client']['acc_person'],
            'address' => $data['client']['address'],
        ]);

        return $client->id;
    }

    /**
     * Compute subtotal (after per-line discount), 20% VAT and grand total.
     */
    private function calculateTotals(array $items): array
    {
        $subtotal = 0;

        foreach ($items as $item) {
            $subtotal += $this->lineTotal($item);
        }

        $vat = round($subtotal * 0.20, 2);

        return [
            'subtotal' => round($subtotal, 2),
            'vat' => $vat,
            'amount' => round($subtotal + $vat, 2),
        ];
    }

    private function lineTotal(array $item): float
    {
        $quantity = (float) $item['quantity'];
        $unitPrice = (float) $item['unit_price'];
        $discount = (float) ($item['discount'] ?? 0);

        return $quantity * $unitPrice * (1 - $discount / 100);
    }

    private function syncItems(Invoice $invoice, array $items): void
    {
        foreach ($items as $item) {
            $invoice->items()->create([
                'article_id' => $item['article_id'] ?? null,
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'discount' => $item['discount'] ?? 0,
                'total' => round($this->lineTotal($item), 2),
            ]);
        }
    }
}
