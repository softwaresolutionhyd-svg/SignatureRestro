<?php

namespace App\Services;

use App\Models\PosOrder;
use App\Models\PosOrderItem;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Sends raw ESC/POS jobs to network thermal printers (port 9100 style) over TCP.
 * Server must be on the same LAN as the printers.
 */
final class NetworkPrinterService
{
    /** Characters per line for an 80mm thermal printer. */
    private const WIDTH = 48;

    // ── ESC/POS control codes ──────────────────────────────────────────────
    private const INIT = "\x1B\x40";              // ESC @  (reset)
    private const BOLD_ON = "\x1B\x45\x01";
    private const BOLD_OFF = "\x1B\x45\x00";
    private const ALIGN_LEFT = "\x1B\x61\x00";
    private const ALIGN_CENTER = "\x1B\x61\x01";
    private const SIZE_NORMAL = "\x1D\x21\x00";
    private const SIZE_DOUBLE = "\x1D\x21\x11";   // double width + height
    private const SIZE_TALL = "\x1D\x21\x01";     // double height
    private const CUT = "\x1D\x56\x42\x00";       // partial cut with feed
    private const FEED = "\x1B\x64\x04";          // feed 4 lines

    /**
     * Open a TCP socket to the printer and write the payload.
     *
     * @throws RuntimeException on connection/write failure.
     */
    public function send(string $ip, int $port, string $payload, int $timeoutSeconds = 5): void
    {
        $ip = trim($ip);
        if ($ip === '') {
            throw new RuntimeException('Printer IP set nahi hai.');
        }
        if ($port <= 0) {
            $port = 9100;
        }

        $errno = 0;
        $errstr = '';
        $fp = @fsockopen($ip, $port, $errno, $errstr, $timeoutSeconds);

        if ($fp === false) {
            throw new RuntimeException(sprintf('Printer %s:%d se connect nahi ho saka (%s).', $ip, $port, $errstr ?: 'timeout'));
        }

        try {
            stream_set_timeout($fp, $timeoutSeconds);
            $written = @fwrite($fp, $payload);
            if ($written === false) {
                throw new RuntimeException(sprintf('Printer %s:%d par data bhejne mein masla.', $ip, $port));
            }
            @fflush($fp);
        } finally {
            @fclose($fp);
        }
    }

    /**
     * Build an ESC/POS kitchen slip for one department's items.
     *
     * @param  Collection<int, PosOrderItem>|iterable<int, PosOrderItem>  $items
     */
    public function buildKitchenSlip(PosOrder $order, string $departmentName, iterable $items, ?string $company = null): string
    {
        $out = self::INIT;

        $out .= self::ALIGN_CENTER . self::SIZE_DOUBLE . self::BOLD_ON;
        $out .= $this->clip(strtoupper($departmentName)) . "\n";
        $out .= self::SIZE_NORMAL . self::BOLD_OFF;

        if ($company) {
            $out .= $this->clip($company) . "\n";
        }
        $out .= self::ALIGN_LEFT;
        $out .= $this->rule();

        // Order meta
        $out .= self::BOLD_ON . $this->twoCol('Bill #: ' . ($order->order_no ?? $order->id), $order->created_at?->format('d-M H:i') ?? '') . self::BOLD_OFF . "\n";

        $where = $this->orderLocation($order);
        if ($where !== '') {
            $out .= $this->line('Table/Room: ' . $where) . "\n";
        }
        if ($order->user?->name) {
            $out .= $this->line('By: ' . $order->user->name) . "\n";
        }
        $out .= $this->rule();

        // Items (qty x name) in double height for kitchen readability
        $out .= self::SIZE_TALL . self::BOLD_ON;
        foreach ($items as $item) {
            $qty = rtrim(rtrim(number_format((float) $item->qty, 3, '.', ''), '0'), '.');
            $name = (string) ($item->product?->name ?? $item->name ?? 'Item');
            $out .= $this->clip($qty . ' x ' . $name) . "\n";

            $notes = trim((string) ($item->notes ?? ''));
            if ($notes !== '') {
                $out .= self::SIZE_NORMAL;
                $out .= $this->line('   * ' . $notes) . "\n";
                $out .= self::SIZE_TALL;
            }
        }
        $out .= self::SIZE_NORMAL . self::BOLD_OFF;

        $out .= $this->rule();
        $out .= self::FEED . self::CUT;

        return $out;
    }

    /**
     * Build an ESC/POS cashier bill.
     *
     * @param  array<string, mixed>  $settings
     */
    public function buildBillSlip(PosOrder $order, array $settings): string
    {
        $currency = (string) ($settings['currency_symbol'] ?? 'Rs.');
        $out = self::INIT;

        $out .= self::ALIGN_CENTER . self::SIZE_DOUBLE . self::BOLD_ON;
        $out .= $this->clip((string) ($settings['company_name'] ?? config('app.name'))) . "\n";
        $out .= self::SIZE_NORMAL . self::BOLD_OFF;

        if (! empty($settings['company_address'])) {
            $out .= $this->clip((string) $settings['company_address']) . "\n";
        }
        if (! empty($settings['company_phone'])) {
            $out .= $this->clip('Ph: ' . $settings['company_phone']) . "\n";
        }

        $out .= self::ALIGN_LEFT . $this->rule();
        $out .= $this->twoCol('Bill #: ' . ($order->order_no ?? $order->id), $order->created_at?->format('d-M H:i') ?? '') . "\n";
        $where = $this->orderLocation($order);
        if ($where !== '') {
            $out .= $this->line('Table/Room: ' . $where) . "\n";
        }
        if ($order->user?->name) {
            $out .= $this->line('Cashier: ' . $order->user->name) . "\n";
        }
        $out .= $this->rule();

        // Column header
        $out .= self::BOLD_ON . $this->itemRow('Item', 'Qty', 'Amount') . self::BOLD_OFF . "\n";
        foreach ($order->items as $item) {
            $qty = rtrim(rtrim(number_format((float) $item->qty, 3, '.', ''), '0'), '.');
            $amount = number_format((float) $item->total, 2);
            $out .= $this->itemRow((string) ($item->product?->name ?? $item->name ?? 'Item'), $qty, $amount) . "\n";
        }
        $out .= $this->rule();

        // Totals
        $out .= $this->twoCol('Subtotal', $currency . ' ' . number_format((float) $order->subtotal, 2)) . "\n";
        if ((float) $order->discount_total > 0) {
            $out .= $this->twoCol('Discount', '-' . $currency . ' ' . number_format((float) $order->discount_total, 2)) . "\n";
        }
        if ((float) ($order->service_charge_total ?? 0) > 0) {
            $out .= $this->twoCol('Service Charges', $currency . ' ' . number_format((float) $order->service_charge_total, 2)) . "\n";
        }
        if ((float) $order->tax_total > 0) {
            $out .= $this->twoCol('Tax', $currency . ' ' . number_format((float) $order->tax_total, 2)) . "\n";
        }
        $out .= self::BOLD_ON . self::SIZE_TALL;
        $out .= $this->twoCol('TOTAL', $currency . ' ' . number_format((float) $order->grand_total, 2)) . "\n";
        $out .= self::SIZE_NORMAL . self::BOLD_OFF;

        $out .= $this->rule();
        $out .= self::ALIGN_CENTER;
        $out .= $this->clip('Thank you!') . "\n";
        $out .= self::ALIGN_LEFT;
        $out .= self::FEED . self::CUT;

        return $out;
    }

    // ── formatting helpers ─────────────────────────────────────────────────

    private function orderLocation(PosOrder $order): string
    {
        if ($order->table?->name) {
            return (string) $order->table->name;
        }
        $room = trim((string) ($order->room_no ?? ''));
        if ($room !== '') {
            return 'Room ' . $room;
        }

        return trim((string) ($order->guest_name ?? ''));
    }

    private function clip(string $text): string
    {
        $text = preg_replace('/\s+/', ' ', trim($text)) ?? '';

        return mb_substr($text, 0, self::WIDTH);
    }

    private function line(string $text): string
    {
        return $this->clip($text);
    }

    private function rule(): string
    {
        return str_repeat('-', self::WIDTH) . "\n";
    }

    private function twoCol(string $left, string $right): string
    {
        $left = trim($left);
        $right = trim($right);
        $space = self::WIDTH - mb_strlen($left) - mb_strlen($right);
        if ($space < 1) {
            $left = mb_substr($left, 0, max(0, self::WIDTH - mb_strlen($right) - 1));
            $space = self::WIDTH - mb_strlen($left) - mb_strlen($right);
        }

        return $left . str_repeat(' ', max(1, $space)) . $right;
    }

    private function itemRow(string $name, string $qty, string $amount): string
    {
        $qtyW = 5;
        $amtW = 10;
        $nameW = self::WIDTH - $qtyW - $amtW;

        $name = mb_substr($name, 0, $nameW);
        $name = $name . str_repeat(' ', max(0, $nameW - mb_strlen($name)));
        $qty = str_repeat(' ', max(0, $qtyW - mb_strlen($qty))) . $qty;
        $amount = str_repeat(' ', max(0, $amtW - mb_strlen($amount))) . $amount;

        return $name . $qty . $amount;
    }
}
