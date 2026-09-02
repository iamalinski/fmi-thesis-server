<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Services\InvoicePdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public ?string $note = null,
    ) {
        $this->invoice->loadMissing('client', 'user.company');
    }

    public function envelope(): Envelope
    {
        $company = $this->invoice->user->company ?? null;

        // The message leaves from the verified Mailgun domain, but replies go
        // back to the company that issued the invoice.
        $replyTo = [];

        if ($company?->email) {
            $replyTo[] = new Address($company->email, $company->name ?? '');
        } elseif ($this->invoice->user?->email) {
            $replyTo[] = new Address($this->invoice->user->email, trim($this->invoice->user->first_name . ' ' . $this->invoice->user->last_name));
        }

        return new Envelope(
            subject: 'Фактура ' . $this->invoice->invoice_number,
            replyTo: $replyTo,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice',
            with: [
                'invoice' => $this->invoice,
                'senderName' => $this->invoice->user->company->name
                    ?? trim($this->invoice->user->first_name . ' ' . $this->invoice->user->last_name),
                'note' => $this->note,
            ],
        );
    }

    public function attachments(): array
    {
        $pdfService = app(InvoicePdfService::class);

        return [
            \Illuminate\Mail\Mailables\Attachment::fromData(
                fn () => $pdfService->getContents($this->invoice),
                $pdfService->downloadName($this->invoice)
            )->withMime('application/pdf'),
        ];
    }
}
