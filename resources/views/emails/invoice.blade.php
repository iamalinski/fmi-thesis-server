<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Фактура {{ $invoice->invoice_number }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f5f7; font-family:Arial, Helvetica, sans-serif; color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f5f7; padding:32px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background-color:#ffffff; border-radius:8px; border:1px solid #e5e7eb;">
                    <tr>
                        <td style="padding:24px 32px; border-bottom:1px solid #e5e7eb;">
                            <h1 style="margin:0; font-size:18px; font-weight:bold; color:#111827;">
                                Фактура {{ $invoice->invoice_number }}
                            </h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px 32px;">
                            <p style="margin:0 0 16px; font-size:15px; line-height:22px;">
                                {{ $senderName }} Ви изпрати фактура. Моля, заплатете я до датата на падежа.
                            </p>

                            <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%; font-size:14px; line-height:20px; margin:0 0 16px;">
                                <tr>
                                    <td style="padding:4px 0; color:#6b7280;">Номер на фактура</td>
                                    <td style="padding:4px 0; text-align:right; font-weight:bold;">{{ $invoice->invoice_number }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:4px 0; color:#6b7280;">Дата на издаване</td>
                                    <td style="padding:4px 0; text-align:right;">{{ $invoice->date?->format('d.m.Y') }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:4px 0; color:#6b7280;">Падеж</td>
                                    <td style="padding:4px 0; text-align:right; font-weight:bold;">{{ $invoice->due_date?->format('d.m.Y') }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:4px 0; color:#6b7280;">Дължима сума</td>
                                    <td style="padding:4px 0; text-align:right; font-weight:bold;">{{ number_format((float) $invoice->amount, 2, '.', ' ') }} EUR</td>
                                </tr>
                            </table>

                            @if ($note)
                                <p style="margin:0 0 16px; padding:12px 16px; background-color:#f9fafb; border-left:3px solid #d1d5db; font-size:14px; line-height:20px; white-space:pre-line;">{{ $note }}</p>
                            @endif

                            <p style="margin:0; font-size:14px; line-height:20px; color:#6b7280;">
                                Фактурата е прикачена към това писмо като PDF файл.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 32px; border-top:1px solid #e5e7eb; font-size:12px; line-height:18px; color:#9ca3af;">
                            {{ $senderName }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
