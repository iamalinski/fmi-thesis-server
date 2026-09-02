<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    public const RECURRENCE_WEEKLY = 'weekly';
    public const RECURRENCE_MONTHLY = 'monthly';

    protected $fillable = [
        'user_id',
        'client_id',
        'sale_id',
        'invoice_number',
        'date',
        'due_date',
        'amount',
        'subtotal',
        'vat',
        'payment_method',
        'deal_location',
        'author',
        'status',
        'notes',
        'is_recurring',
        'recurrence_type',
        'recurrence_day_of_week',
        'recurrence_day_of_month',
        'recurrence_next_run_at',
        'recurrence_last_run_at',
        'recurrence_parent_id',
    ];

    protected $casts = [
        'date' => 'date',
        'due_date' => 'date',
        'amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'vat' => 'decimal:2',
        'is_recurring' => 'boolean',
        'recurrence_day_of_week' => 'integer',
        'recurrence_day_of_month' => 'integer',
        'recurrence_next_run_at' => 'date',
        'recurrence_last_run_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * The recurring template this invoice was generated from, if any.
     */
    public function recurrenceParent()
    {
        return $this->belongsTo(Invoice::class, 'recurrence_parent_id');
    }

    /**
     * Invoices the scheduler has generated from this template.
     */
    public function recurrenceChildren()
    {
        return $this->hasMany(Invoice::class, 'recurrence_parent_id');
    }

    /**
     * Recurring templates that are due to emit a copy on or before $date.
     */
    public function scopeDueForRecurrence(Builder $query, CarbonImmutable $date): Builder
    {
        return $query->where('is_recurring', true)
            ->whereNotNull('recurrence_type')
            ->whereNotNull('recurrence_next_run_at')
            ->whereDate('recurrence_next_run_at', '<=', $date->toDateString());
    }

    /**
     * The next date this invoice should be regenerated on, strictly after
     * $after. Returns null when the invoice is not set to repeat.
     */
    public function nextRecurrenceDate(CarbonImmutable $after): ?CarbonImmutable
    {
        if (! $this->is_recurring) {
            return null;
        }

        $after = $after->startOfDay();

        return match ($this->recurrence_type) {
            self::RECURRENCE_WEEKLY => $this->nextWeeklyDate($after),
            self::RECURRENCE_MONTHLY => $this->nextMonthlyDate($after),
            default => null,
        };
    }

    /**
     * The first day after $after that falls on the chosen weekday.
     */
    private function nextWeeklyDate(CarbonImmutable $after): ?CarbonImmutable
    {
        $dayOfWeek = $this->recurrence_day_of_week;

        if ($dayOfWeek === null) {
            return null;
        }

        // Carbon weekday constants run Sunday(0)..Saturday(6); ours run
        // Monday(1)..Sunday(7), so 7 maps back onto 0.
        return $after->next($dayOfWeek % 7);
    }

    /**
     * The first day after $after matching the chosen day of the month, with
     * the day clamped to the last day of shorter months.
     */
    private function nextMonthlyDate(CarbonImmutable $after): ?CarbonImmutable
    {
        $dayOfMonth = $this->recurrence_day_of_month;

        if ($dayOfMonth === null) {
            return null;
        }

        $candidate = $this->clampToMonth($after, $dayOfMonth);

        if ($candidate->lessThanOrEqualTo($after)) {
            $candidate = $this->clampToMonth($after->startOfMonth()->addMonth(), $dayOfMonth);
        }

        return $candidate;
    }

    /**
     * Place $dayOfMonth inside the month of $month, never overflowing into
     * the following month (31 => 30 in April, 28/29 in February).
     */
    private function clampToMonth(CarbonImmutable $month, int $dayOfMonth): CarbonImmutable
    {
        $month = $month->startOfDay();

        return $month->day(min($dayOfMonth, $month->daysInMonth));
    }

    /**
     * Build the next sequential 10-digit, zero-padded invoice number
     * (e.g. 0000000101). The sequence is derived from the highest existing
     * numeric invoice number, so legacy non-numeric numbers are ignored.
     */
    public static function nextInvoiceNumber(): string
    {
        $max = (int) static::query()
            ->selectRaw('MAX(CAST(invoice_number AS UNSIGNED)) as max_num')
            ->value('max_num');

        return str_pad($max + 1, 10, '0', STR_PAD_LEFT);
    }
}
