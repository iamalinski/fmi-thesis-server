<?php

namespace App\Services;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class InvoicePdfService
{
    /**
     * Payment method labels shown on the printed invoice.
     */
    private const PAYMENT_LABELS = [
        'Cash' => 'В брой',
        'Bank' => 'Банков превод',
    ];

    /**
     * Render the invoice into a PDF and persist it to the local disk.
     * Returns the storage-relative path of the generated file.
     */
    public function generateAndStore(Invoice $invoice): string
    {
        $pdf = $this->makePdf($invoice);

        $path = $this->pathFor($invoice);
        Storage::disk('local')->put($path, $pdf->output());

        return $path;
    }

    /**
     * Return the stored PDF contents, generating the file first if missing.
     */
    public function getContents(Invoice $invoice): string
    {
        $path = $this->pathFor($invoice);

        if (! Storage::disk('local')->exists($path)) {
            $this->generateAndStore($invoice);
        }

        return Storage::disk('local')->get($path);
    }

    /**
     * Remove the stored PDF (e.g. so it is regenerated on next request).
     */
    public function delete(Invoice $invoice): void
    {
        Storage::disk('local')->delete($this->pathFor($invoice));
    }

    /**
     * A safe, human-friendly download file name.
     */
    public function downloadName(Invoice $invoice): string
    {
        return $invoice->invoice_number . '.pdf';
    }

    private function pathFor(Invoice $invoice): string
    {
        return 'invoices/invoice-' . $invoice->id . '.pdf';
    }

    private function makePdf(Invoice $invoice)
    {
        $invoice->loadMissing('client', 'items', 'user.company');

        return Pdf::loadView('invoices.pdf', [
            'invoice' => $invoice,
            'company' => $invoice->user->company ?? null,
            'paymentMethodLabel' => self::PAYMENT_LABELS[$invoice->payment_method] ?? ($invoice->payment_method ?: '—'),
            'amountInWords' => $this->amountInWords((float) $invoice->amount),
        ])->setPaper('a4');
    }

    /**
     * Convert a monetary amount into Bulgarian words, e.g.
     * 1234.56 => "хиляда двеста тридесет и четири евро и петдесет и шест евроцента".
     */
    public function amountInWords(float $amount): string
    {
        $euro = (int) floor($amount);
        $cents = (int) round(($amount - $euro) * 100);

        // Guard against floating point rounding pushing cents to 100
        if ($cents === 100) {
            $euro++;
            $cents = 0;
        }

        // "евро" is neuter and invariant; "евроцент" is masculine
        $euroWords = $this->integerToWords($euro, 'n');

        $centsWords = $this->integerToWords($cents, 'm');
        $centsNoun = $cents === 1 ? 'евроцент' : 'евроцента';

        return ucfirst($euroWords) . ' евро и ' . $centsWords . ' ' . $centsNoun;
    }

    /**
     * Convert an integer (0..999 999 999) into Bulgarian words.
     * $gender applies to the units 1/2 ("един/два" vs "една/две").
     */
    private function integerToWords(int $n, string $gender = 'm'): string
    {
        if ($n === 0) {
            return 'нула';
        }

        $millions = intdiv($n, 1000000);
        $remainderAfterMillions = $n % 1000000;
        $thousands = intdiv($remainderAfterMillions, 1000);
        $rest = $remainderAfterMillions % 1000;

        $segments = [];

        if ($millions > 0) {
            $segments[] = $millions === 1
                ? 'един милион'
                : $this->groupToWords($millions, 'm') . ' милиона';
        }

        if ($thousands > 0) {
            $segments[] = $thousands === 1
                ? 'хиляда'
                : $this->groupToWords($thousands, 'f') . ' хиляди';
        }

        if ($rest > 0) {
            $segments[] = $this->groupToWords($rest, $gender);
        }

        return $this->joinSegments($segments, $rest, $thousands, $millions);
    }

    /**
     * Join top-level segments, placing "и" before the final one where the
     * Bulgarian grammar calls for it (last group below 100 or exact hundreds).
     */
    private function joinSegments(array $segments, int $rest, int $thousands, int $millions): string
    {
        if (count($segments) === 1) {
            return $segments[0];
        }

        $last = array_pop($segments);

        $lastValue = $rest > 0
            ? $rest
            : ($thousands > 0 ? $thousands * 1000 : $millions * 1000000);

        $connector = ($lastValue < 100 || $lastValue % 100 === 0) ? ' и ' : ' ';

        return implode(' ', $segments) . $connector . $last;
    }

    /**
     * Convert a 0..999 group into words.
     */
    private function groupToWords(int $n, string $gender): string
    {
        $ones = ['', 'едно', 'две', 'три', 'четири', 'пет', 'шест', 'седем', 'осем', 'девет'];
        $teens = ['десет', 'единадесет', 'дванадесет', 'тринадесет', 'четиринадесет', 'петнадесет', 'шестнадесет', 'седемнадесет', 'осемнадесет', 'деветнадесет'];
        $tens = ['', '', 'двадесет', 'тридесет', 'четиридесет', 'петдесет', 'шестдесет', 'седемдесет', 'осемдесет', 'деветдесет'];
        $hundreds = ['', 'сто', 'двеста', 'триста', 'четиристотин', 'петстотин', 'шестстотин', 'седемстотин', 'осемстотин', 'деветстотин'];

        $h = intdiv($n, 100);
        $remainder = $n % 100;

        $parts = [];

        if ($h > 0) {
            $parts[] = $hundreds[$h];
        }

        if ($remainder >= 10 && $remainder <= 19) {
            $parts[] = $teens[$remainder - 10];
        } else {
            $t = intdiv($remainder, 10);
            $u = $remainder % 10;

            if ($t > 0) {
                $parts[] = $tens[$t];
            }

            if ($u > 0) {
                $parts[] = $this->unitWord($u, $gender, $ones);
            }
        }

        if (count($parts) <= 1) {
            return $parts[0] ?? '';
        }

        $last = array_pop($parts);

        return implode(' ', $parts) . ' и ' . $last;
    }

    private function unitWord(int $u, string $gender, array $ones): string
    {
        if ($u === 1) {
            return match ($gender) {
                'f' => 'една',
                'n' => 'едно',
                default => 'един',
            };
        }

        if ($u === 2) {
            // feminine and neuter share "две"; masculine is "два"
            return $gender === 'm' ? 'два' : 'две';
        }

        return $ones[$u];
    }
}
