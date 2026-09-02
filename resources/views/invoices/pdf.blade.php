<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="utf-8">
    <style>
        * {
            font-family: 'DejaVu Sans', sans-serif;
            box-sizing: border-box;
        }

        @page {
            margin: 24px 32px;
        }

        body {
            font-size: 11px;
            color: #1a1a1a;
            margin: 0;
        }

        .header {
            width: 100%;
            margin-bottom: 12px;
        }

        .header td {
            vertical-align: top;
        }

        .doc-title {
            font-size: 20px;
            font-weight: bold;
            letter-spacing: 1px;
        }

        .doc-meta {
            text-align: right;
            font-size: 11px;
        }

        .doc-meta .num {
            font-size: 15px;
            font-weight: bold;
        }

        .original {
            display: inline-block;
            border: 1px solid #444;
            padding: 2px 8px;
            font-size: 10px;
            margin-top: 4px;
        }

        .parties {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }

        .parties td {
            width: 50%;
            vertical-align: top;
            border: 1px solid #cccccc;
            padding: 8px 10px;
        }

        .party-label {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #777;
            margin-bottom: 4px;
        }

        .party-name {
            font-weight: bold;
            font-size: 12px;
            margin-bottom: 4px;
        }

        .party-row {
            margin: 1px 0;
        }

        .party-row .k {
            color: #555;
            display: inline-block;
            min-width: 74px;
        }

        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        table.items th {
            background: #f0f2f5;
            border: 1px solid #cccccc;
            padding: 6px 8px;
            font-size: 10px;
            text-align: left;
        }

        table.items td {
            border: 1px solid #dddddd;
            padding: 6px 8px;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .totals {
            width: 100%;
            margin-bottom: 14px;
        }

        .totals td {
            vertical-align: top;
        }

        .words {
            font-style: italic;
            padding-top: 6px;
            font-size: 10px;
            color: #333;
        }

        table.totals-box {
            border-collapse: collapse;
            width: 100%;
        }

        table.totals-box td {
            padding: 4px 8px;
            border: 1px solid #dddddd;
        }

        table.totals-box td.label {
            color: #555;
        }

        table.totals-box tr.grand td {
            background: #f0f2f5;
            font-weight: bold;
            font-size: 13px;
        }

        .meta-strip {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }

        .meta-strip td {
            border: 1px solid #dddddd;
            padding: 5px 8px;
        }

        .meta-strip .k {
            font-size: 9px;
            text-transform: uppercase;
            color: #777;
            display: block;
        }

        .signatures {
            width: 100%;
            margin-top: 28px;
        }

        .signatures td {
            width: 50%;
            padding-top: 20px;
            font-size: 10px;
            color: #333;
        }

        .sign-line {
            border-top: 1px solid #999;
            width: 180px;
            padding-top: 3px;
        }

        .footer-note {
            margin-top: 24px;
            font-size: 8px;
            color: #999;
            text-align: center;
        }
    </style>
</head>
<body>

    <table class="header">
        <tr>
            <td>
                <div class="doc-title">ФАКТУРА</div>
                <div class="original">ОРИГИНАЛ</div>
            </td>
            <td class="doc-meta">
                <div>№ <span class="num">{{ $invoice->invoice_number }}</span></div>
                <div>Дата на издаване: {{ \Illuminate\Support\Carbon::parse($invoice->date)->format('d.m.Y') }} г.</div>
                @if ($invoice->deal_location)
                    <div>Място на издаване: {{ $invoice->deal_location }}</div>
                @endif
            </td>
        </tr>
    </table>

    <table class="parties">
        <tr>
            <td>
                <div class="party-label">Получател</div>
                <div class="party-name">{{ $invoice->client->name ?? '—' }}</div>
                @if (!empty($invoice->client->number))
                    <div class="party-row"><span class="k">ЕИК/Булстат:</span> {{ $invoice->client->number }}</div>
                @endif
                @if (!empty($invoice->client->vat_number))
                    <div class="party-row"><span class="k">Ид. № по ДДС:</span> {{ $invoice->client->vat_number }}</div>
                @endif
                @if (!empty($invoice->client->address))
                    <div class="party-row"><span class="k">Адрес:</span> {{ $invoice->client->address }}</div>
                @endif
                @if (!empty($invoice->client->acc_person))
                    <div class="party-row"><span class="k">МОЛ:</span> {{ $invoice->client->acc_person }}</div>
                @endif
            </td>
            <td>
                <div class="party-label">Доставчик</div>
                <div class="party-name">{{ $company->name ?? '—' }}</div>
                @if (!empty($company->eik))
                    <div class="party-row"><span class="k">ЕИК/Булстат:</span> {{ $company->eik }}</div>
                @endif
                @if (!empty($company->vat_number))
                    <div class="party-row"><span class="k">Ид. № по ДДС:</span> {{ $company->vat_number }}</div>
                @endif
                @if (!empty($company->address))
                    <div class="party-row"><span class="k">Адрес:</span> {{ $company->address }}</div>
                @endif
                @if (!empty($company->mol))
                    <div class="party-row"><span class="k">МОЛ:</span> {{ $company->mol }}</div>
                @endif
                @if (!empty($company->bank_name) || !empty($company->bank_account))
                    <div class="party-row"><span class="k">Банка:</span> {{ $company->bank_name }}</div>
                    <div class="party-row"><span class="k">IBAN:</span> {{ $company->bank_account }}</div>
                @endif
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 26px;">№</th>
                <th>Наименование на стоката/услугата</th>
                <th style="width: 70px;" class="text-right">К-во</th>
                <th style="width: 80px;" class="text-right">Ед. цена</th>
                <th style="width: 70px;" class="text-right">Отстъпка</th>
                <th style="width: 90px;" class="text-right">Стойност</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $index => $item)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $item->description }}</td>
                    <td class="text-right">{{ rtrim(rtrim(number_format($item->quantity, 2, '.', ' '), '0'), '.') }}</td>
                    <td class="text-right">{{ number_format($item->unit_price, 2, '.', ' ') }} EUR</td>
                    <td class="text-right">{{ number_format($item->discount, 0) }}%</td>
                    <td class="text-right">{{ number_format($item->total, 2, '.', ' ') }} EUR</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>
                <div class="words">
                    <strong>Словом:</strong> {{ $amountInWords }}
                </div>
            </td>
            <td style="width: 260px;">
                <table class="totals-box">
                    <tr>
                        <td class="label">Данъчна основа</td>
                        <td class="text-right">{{ number_format($invoice->subtotal, 2, '.', ' ') }} EUR</td>
                    </tr>
                    <tr>
                        <td class="label">ДДС 20%</td>
                        <td class="text-right">{{ number_format($invoice->vat, 2, '.', ' ') }} EUR</td>
                    </tr>
                    <tr class="grand">
                        <td>Сума за плащане</td>
                        <td class="text-right">{{ number_format($invoice->amount, 2, '.', ' ') }} EUR</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="meta-strip">
        <tr>
            <td style="width: 34%;">
                <span class="k">Начин на плащане</span>
                {{ $paymentMethodLabel }}
            </td>
            <td style="width: 33%;">
                <span class="k">Дата на данъчно събитие</span>
                {{ \Illuminate\Support\Carbon::parse($invoice->date)->format('d.m.Y') }} г.
            </td>
            <td style="width: 33%;">
                <span class="k">Падеж</span>
                {{ \Illuminate\Support\Carbon::parse($invoice->due_date)->format('d.m.Y') }} г.
            </td>
        </tr>
    </table>

    <table class="signatures">
        <tr>
            <td>
                <div class="sign-line">Съставил: {{ $invoice->author ?? '' }}</div>
            </td>
            <td class="text-right">
                <div class="sign-line" style="float: right;">Получил: {{ $invoice->client->acc_person ?? '' }}</div>
            </td>
        </tr>
    </table>

</body>
</html>
