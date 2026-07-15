<?php

namespace App\Services;

use App\Models\InventoryDepartment;
use App\Models\InventoryProduct;
use App\Models\PosOrder;
use App\Models\PosOrderItem;
use App\Models\Setting;
use App\Support\EnsuresKitchenAgentSchema;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Sends raw ESC/POS jobs to network thermal printers (port 9100 style) over TCP.
 * Server must be on the same LAN as the printers.
 */
final class NetworkPrinterService
{
    use EnsuresKitchenAgentSchema;

    /** Characters per line for an 80mm thermal printer. */
    private const WIDTH = 48;

    // ── ESC/POS control codes ──────────────────────────────────────────────
    private const INIT = "\x1B\x40";              // ESC @  (reset)
    private const BOLD_ON = "\x1B\x45\x01";
    private const BOLD_OFF = "\x1B\x45\x00";
    private const ALIGN_LEFT = "\x1B\x61\x00";
    private const ALIGN_CENTER = "\x1B\x61\x01";
    private const SIZE_NORMAL = "\x1D\x21\x00";
    private const SIZE_WIDE = "\x1D\x21\x10";    // double width, normal height (big, not stretched down)
    private const SIZE_DOUBLE = "\x1D\x21\x11";   // double width + height (headers only)
    private const SIZE_TALL = "\x1D\x21\x01";     // double height only — avoid for body text
    private const CUT = "\x1D\x56\x42\x00";       // partial cut with feed
    private const FEED = "\x1B\x64\x04";          // feed 4 lines
    /** Usable chars per line when width is doubled. */
    private const WIDTH_DOUBLE = 24;

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
     * Print all pending kitchen items to their department printers.
     *
     * @return array{
     *   ok: bool,
     *   fallback?: bool,
     *   message?: string,
     *   results: list<array{department: string, ok: bool, error?: string}>,
     *   unrouted: int
     * }
     */
    public function dispatchPendingKitchenPrints(PosOrder $order): array
    {
        $this->ensureKitchenAgentSchema();

        $order->loadMissing([
            'items.product:id,name,sku,department_id',
            'items.product.departments:id,name,printer_ip,printer_port',
            'items.product.department:id,name,printer_ip,printer_port',
            'user:id,name',
            'table:id,name',
        ]);

        $kitchenItems = $order->items
            ->filter(fn (PosOrderItem $item) => (bool) $item->kitchen_pending && ! $item->isKitchenServed())
            ->values();

        if ($kitchenItems->isEmpty()) {
            return [
                'ok' => false,
                'message' => 'Koi kitchen item pending nahi.',
                'results' => [],
                'unrouted' => 0,
            ];
        }

        $groups = [];
        $unrouted = 0;
        foreach ($kitchenItems as $item) {
            $dept = $this->resolveItemDepartment($item->product);
            if ($dept && ! empty($dept->printer_ip)) {
                $groups[$dept->id]['dept'] = $dept;
                $groups[$dept->id]['items'][] = $item;
            } else {
                $unrouted++;
            }
        }

        if ($groups === []) {
            return [
                'ok' => false,
                'fallback' => true,
                'message' => 'Kisi department ka printer set nahi (Inventory → Kitchen Agents).',
                'results' => [],
                'unrouted' => $unrouted,
            ];
        }

        $company = Setting::get('company_name', config('app.name'));
        $results = [];

        foreach ($groups as $group) {
            /** @var InventoryDepartment $dept */
            $dept = $group['dept'];
            $payload = $this->buildKitchenSlip($order, (string) $dept->name, $group['items'], $company);

            try {
                $this->send((string) $dept->printer_ip, (int) ($dept->printer_port ?: 9100), $payload);
                $results[] = ['department' => (string) $dept->name, 'ok' => true];
            } catch (\Throwable $e) {
                $results[] = [
                    'department' => (string) $dept->name,
                    'ok' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $anyOk = collect($results)->contains(fn ($r) => $r['ok'] === true);

        return [
            'ok' => $anyOk,
            'results' => $results,
            'unrouted' => $unrouted,
            'message' => $anyOk ? null : 'Kitchen print fail hua.',
        ];
    }

    /**
     * Resolve which department (with a printer) a product should print to.
     */
    public function resolveItemDepartment(?InventoryProduct $product): ?InventoryDepartment
    {
        if ($product === null) {
            return null;
        }

        $depts = $product->relationLoaded('departments')
            ? $product->departments
            : collect();

        // Prefer tagged departments that have a printer.
        if ($depts->isNotEmpty()) {
            $primary = $depts->firstWhere('id', $product->department_id);
            if ($primary && ! empty($primary->printer_ip)) {
                return $primary;
            }

            $withPrinter = $depts->first(fn ($d) => ! empty($d->printer_ip));
            if ($withPrinter) {
                return $withPrinter;
            }

            if ($primary) {
                return $primary;
            }
        }

        // Fallback: product.primary department (belongsTo), even without pivot tags.
        $primaryDept = $product->relationLoaded('department')
            ? $product->department
            : $product->department()->first(['id', 'name', 'printer_ip', 'printer_port']);

        return $primaryDept instanceof InventoryDepartment ? $primaryDept : ($depts->first() ?: null);
    }

    /**
     * Build an ESC/POS kitchen slip for one department's items.
     *
     * Layout:
     *   Department (center) → Company (center) → Bill# / DateTime
     *   → Table No (center, large) → by: name → Complete bill Notes
     *   → Items | QTY → *notes → blank lines → END → Cut
     *
     * @param  Collection<int, PosOrderItem>|iterable<int, PosOrderItem>  $items
     */
    public function buildKitchenSlip(PosOrder $order, string $departmentName, iterable $items, ?string $company = null): string
    {
        $companyName = strtoupper(trim((string) ($company ?: '')));
        $companyName = preg_replace('/\bRESRO\b/u', 'RESTRO', $companyName) ?? $companyName;
        if ($companyName === '') {
            $companyName = 'SIGNATURE RESTRO';
        }

        $out = self::INIT;

        // Department Name (center) — proportional double size
        $out .= self::ALIGN_CENTER . self::SIZE_DOUBLE . self::BOLD_ON;
        $out .= $this->clipWide(strtoupper(trim($departmentName))) . "\n";
        $out .= self::SIZE_NORMAL . self::BOLD_OFF;

        // Company (center)
        $out .= self::ALIGN_CENTER . self::BOLD_ON;
        $out .= $this->clip($companyName) . "\n";
        $out .= self::BOLD_OFF;

        // Bill#                               Date/Time
        $out .= self::ALIGN_LEFT;
        $billLeft = 'Bill#: ' . ($order->order_no ?? $order->id);
        $billRight = now()->format('d-M-Y h:i A');
        $out .= $this->twoCol($billLeft, $billRight) . "\n";

        // Table No# (center, tall+bold — big font, normal letter spacing)
        $tableNo = $this->kitchenTableLabel($order);
        if ($tableNo !== '') {
            $out .= "\n" . self::ALIGN_CENTER . self::SIZE_TALL . self::BOLD_ON;
            $out .= $this->clip($tableNo) . "\n";
            $out .= self::SIZE_NORMAL . self::BOLD_OFF;
        }

        // Service type under table
        $serviceTag = $this->kitchenServiceTag($order);
        if ($serviceTag !== '') {
            $out .= self::ALIGN_CENTER . self::BOLD_ON;
            $out .= $this->clip($serviceTag) . "\n";
            $out .= self::BOLD_OFF;
        }

        // by: (cashier / order taker)
        $out .= self::ALIGN_LEFT;
        if ($order->user?->name) {
            $out .= $this->line('by: ' . $order->user->name) . "\n";
        }

        // Complete bill Notes (if added)
        $billNotes = trim((string) ($order->kitchen_notes ?? ''));
        if ($billNotes !== '') {
            $out .= self::BOLD_ON . $this->line('Complete bill Notes:') . self::BOLD_OFF . "\n";
            foreach (preg_split("/\r\n|\n|\r/", $billNotes) ?: [] as $noteLine) {
                $noteLine = trim((string) $noteLine);
                if ($noteLine === '') {
                    continue;
                }
                $out .= $this->line($noteLine) . "\n";
            }
        }

        $out .= $this->rule();

        // Items | QTY — bold + tall (bara font), normal letter spacing; qty centered under QTY
        $out .= self::BOLD_ON . $this->kitchenItemRow('Items', 'QTY') . self::BOLD_OFF . "\n";
        foreach ($items as $item) {
            $qty = rtrim(rtrim(number_format((float) $item->qty, 3, '.', ''), '0'), '.');
            $name = (string) ($item->product?->name ?? $item->name ?? 'Item');
            $out .= self::SIZE_TALL . self::BOLD_ON;
            $out .= $this->kitchenItemRow($name, $qty) . "\n";
            $out .= self::SIZE_NORMAL . self::BOLD_OFF;

            $notes = trim((string) ($item->notes ?? ''));
            if ($notes !== '') {
                $out .= $this->line('*' . $notes) . "\n";
            }
            $out .= "\n"; // gap between item lines
        }

        // 2 blank lines, then END, then cut
        $out .= "\n\n";
        $out .= self::ALIGN_CENTER . self::BOLD_ON;
        $out .= "END\n";
        $out .= self::BOLD_OFF . self::ALIGN_LEFT;
        $out .= self::CUT;

        return $out;
    }

    private function kitchenTableLabel(PosOrder $order): string
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

    private function kitchenServiceTag(PosOrder $order): string
    {
        return match ($order->serviceTypeKey()) {
            PosOrder::SERVICE_DINE_IN => 'DINE-IN',
            PosOrder::SERVICE_TAKEAWAY => 'TAKEAWAY',
            PosOrder::SERVICE_DELIVERY => 'DELIVERY',
            default => '',
        };
    }

    /**
     * Build an ESC/POS cashier bill.
     *
     * @param  array<string, mixed>  $settings
     */
    public function buildBillSlip(PosOrder $order, array $settings): string
    {
        $currency = (string) ($settings['currency_symbol'] ?? 'Rs.');
        $company = strtoupper(trim((string) ($settings['company_name'] ?? config('app.name'))));
        $company = preg_replace('/\bRESRO\b/u', 'RESTRO', $company) ?? $company;
        $isPaid = strtolower((string) ($order->status ?? '')) === 'paid'
            || filled($order->paid_at);
        $statusLabel = $order->type === 'refund' ? 'REFUND' : ($isPaid ? 'PAID' : 'UNPAID');
        $orderType = $order->serviceTypeLabel() ?: '—';

        $out = self::INIT;

        // Logo (ESC/POS raster) — from Settings → company logo
        $logoPath = (string) ($settings['company_logo_abs_path'] ?? '');
        if ($logoPath === '') {
            $logoPath = company_logo_path((string) ($settings['company_logo'] ?? '')) ?? '';
        }
        $logoBytes = $this->escposLogoRaster($logoPath !== '' ? $logoPath : null);
        if ($logoBytes !== null) {
            $out .= self::ALIGN_CENTER . $logoBytes . "\n";
        }

        // Brand header (tall + bold — double-width strips chars on 58mm paper)
        $out .= self::ALIGN_CENTER . self::SIZE_TALL . self::BOLD_ON;
        $out .= $this->clip($company !== '' ? $company : 'SIGNATURE RESTRO') . "\n";
        $out .= self::SIZE_NORMAL . self::BOLD_OFF;
        $out .= "\n";

        if (! empty(trim((string) ($settings['company_address'] ?? '')))) {
            $out .= $this->clip('Address: ' . $settings['company_address']) . "\n";
        }
        if (! empty(trim((string) ($settings['company_email'] ?? '')))) {
            $out .= $this->clip('Email: ' . $settings['company_email']) . "\n";
        }
        if (! empty(trim((string) ($settings['company_phone'] ?? '')))) {
            $out .= $this->clip('Phone: ' . $settings['company_phone']) . "\n";
        }

        $out .= self::ALIGN_LEFT . "\n" . $this->rule();
        $out .= $this->line('Invoice Number: ' . ($order->order_no ?? $order->id)) . "\n";
        $out .= $this->line('Order Type: ' . $orderType) . "\n";
        $where = $this->orderLocation($order);
        if ($where !== '') {
            $out .= $this->line('Table/Room: ' . $where) . "\n";
        }
        if ($order->user?->name) {
            $out .= $this->line('Cashier: ' . $order->user->name) . "\n";
        }
        $out .= $this->rule() . "\n";

        // Column header: ITEMS | QTY | RATE | AMOUNT
        $out .= self::BOLD_ON . $this->itemRow4('ITEMS', 'QTY', 'RATE', 'AMOUNT') . self::BOLD_OFF . "\n";
        $out .= $this->rule();
        foreach ($order->items as $item) {
            $qty = rtrim(rtrim(number_format((float) $item->qty, 3, '.', ''), '0'), '.');
            $rate = number_format((float) ($item->unit_price ?? 0), 2);
            $amount = number_format((float) $item->total, 2);
            $out .= $this->itemRow4(
                (string) ($item->product?->name ?? $item->name ?? 'Item'),
                $qty,
                $rate,
                $amount
            ) . "\n\n";
        }
        $out .= $this->rule();

        // Totals
        $out .= $this->twoCol('Sub Total', number_format((float) $order->subtotal, 2)) . "\n";
        if ((float) $order->discount_total > 0) {
            $out .= $this->twoCol('Discount', '-' . number_format((float) $order->discount_total, 2)) . "\n";
        }
        if ((float) ($order->service_charge_total ?? 0) > 0) {
            $out .= $this->twoCol('Service Charges', number_format((float) $order->service_charge_total, 2)) . "\n";
        }
        if ((float) $order->tax_total > 0) {
            $out .= $this->twoCol('Tax', number_format((float) $order->tax_total, 2)) . "\n";
        }

        $grandLabel = 'Grand Total';
        $grandAmount = $currency . ' ' . number_format((float) $order->grand_total, 2);
        $out .= "\n" . self::ALIGN_CENTER . self::BOLD_ON . self::SIZE_TALL;
        $out .= $this->clip($grandLabel) . "\n";
        $out .= $this->clip($grandAmount) . "\n";
        $out .= self::SIZE_NORMAL . self::BOLD_OFF;

        $out .= "\n" . $this->rule();
        $out .= self::ALIGN_CENTER . self::SIZE_DOUBLE . self::BOLD_ON;
        $out .= $this->clip($statusLabel) . "\n";
        $out .= self::SIZE_NORMAL . self::BOLD_OFF;
        $out .= "\n\n"; // ~1–2 blank lines before footer
        $out .= self::ALIGN_CENTER;
        $out .= $this->clip('Powered by softwaresolutions.pk') . "\n";
        $out .= self::ALIGN_LEFT;
        $out .= self::FEED . self::CUT;

        return $out;
    }

    // ── formatting helpers ─────────────────────────────────────────────────

    /**
     * Convert a PNG/JPG logo into ESC/POS raster bytes (GS v 0).
     * Returns null when GD is unavailable or the file cannot be read.
     */
    private function escposLogoRaster(?string $absolutePath, int $maxWidthDots = 384): ?string
    {
        if ($absolutePath === null || $absolutePath === '' || ! is_file($absolutePath)) {
            return null;
        }
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $raw = @file_get_contents($absolutePath);
        if ($raw === false || $raw === '') {
            return null;
        }

        $src = @imagecreatefromstring($raw);
        if ($src === false) {
            return null;
        }

        $srcW = imagesx($src);
        $srcH = imagesy($src);
        if ($srcW < 1 || $srcH < 1) {
            imagedestroy($src);

            return null;
        }

        $dstW = min($maxWidthDots, $srcW);
        // Keep even width in bytes for printer alignment.
        $dstW = (int) (floor($dstW / 8) * 8);
        if ($dstW < 8) {
            $dstW = 8;
        }
        $dstH = (int) max(1, round($srcH * ($dstW / $srcW)));

        $dst = imagecreatetruecolor($dstW, $dstH);
        if ($dst === false) {
            imagedestroy($src);

            return null;
        }

        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefill($dst, 0, 0, $white);
        imagealphablending($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
        imagedestroy($src);

        // Threshold to 1-bit (dark pixels print).
        $bytesPerRow = (int) ($dstW / 8);
        $bitmap = '';
        for ($y = 0; $y < $dstH; $y++) {
            for ($byte = 0; $byte < $bytesPerRow; $byte++) {
                $value = 0;
                for ($bit = 0; $bit < 8; $bit++) {
                    $x = $byte * 8 + $bit;
                    $rgb = imagecolorat($dst, $x, $y);
                    $r = ($rgb >> 16) & 0xFF;
                    $g = ($rgb >> 8) & 0xFF;
                    $b = $rgb & 0xFF;
                    $luma = (int) (0.299 * $r + 0.587 * $g + 0.114 * $b);
                    if ($luma < 160) {
                        $value |= 1 << (7 - $bit);
                    }
                }
                $bitmap .= chr($value);
            }
        }
        imagedestroy($dst);

        // GS v 0 m xL xH yL yH d1…dk
        $xL = $bytesPerRow & 0xFF;
        $xH = ($bytesPerRow >> 8) & 0xFF;
        $yL = $dstH & 0xFF;
        $yH = ($dstH >> 8) & 0xFF;

        return "\x1D\x76\x30\x00" . chr($xL) . chr($xH) . chr($yL) . chr($yH) . $bitmap;
    }

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

    private function clipWide(string $text): string
    {
        $text = preg_replace('/\s+/', ' ', trim($text)) ?? '';

        return mb_substr($text, 0, self::WIDTH_DOUBLE);
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
        return $this->twoColAt($left, $right, self::WIDTH);
    }

    /** Two columns for SIZE_DOUBLE / SIZE_WIDE lines (half char budget). */
    private function twoColWide(string $left, string $right): string
    {
        return $this->twoColAt($left, $right, self::WIDTH_DOUBLE);
    }

    private function twoColAt(string $left, string $right, int $width): string
    {
        $left = trim($left);
        $right = trim($right);
        $space = $width - mb_strlen($left) - mb_strlen($right);
        if ($space < 1) {
            $left = mb_substr($left, 0, max(0, $width - mb_strlen($right) - 1));
            $space = $width - mb_strlen($left) - mb_strlen($right);
        }

        return $left . str_repeat(' ', max(1, $space)) . $right;
    }

    /**
     * Kitchen Items | QTY row: name left, qty centered under QTY header,
     * with right margin so numbers are not stuck on the paper edge.
     */
    private function kitchenItemRow(string $name, string $qty): string
    {
        $edge = 2;   // keep off the right edge
        $qtyCol = 6; // wide enough for "QTY" / numbers, centered under header
        $nameW = self::WIDTH - $qtyCol - $edge;

        $name = preg_replace('/\s+/', ' ', trim($name)) ?? '';
        $name = mb_substr($name, 0, max(1, $nameW));
        $namePad = $name . str_repeat(' ', max(0, $nameW - mb_strlen($name)));

        $qty = trim($qty);
        $qty = mb_substr($qty, 0, $qtyCol);
        $pad = max(0, $qtyCol - mb_strlen($qty));
        $left = intdiv($pad, 2);
        $right = $pad - $left;
        $qtyCell = str_repeat(' ', $left) . $qty . str_repeat(' ', $right);

        return $namePad . $qtyCell . str_repeat(' ', $edge);
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

    /** ITEMS | QTY | RATE | AMOUNT — 48-char thermal line. */
    private function itemRow4(string $name, string $qty, string $rate, string $amount): string
    {
        $qtyW = 6;
        $rateW = 9;
        $amtW = 10;
        $nameW = self::WIDTH - $qtyW - $rateW - $amtW;

        $name = mb_substr($name, 0, $nameW);
        $name = $name . str_repeat(' ', max(0, $nameW - mb_strlen($name)));
        $qty = str_repeat(' ', max(0, $qtyW - mb_strlen($qty))) . $qty;
        $rate = str_repeat(' ', max(0, $rateW - mb_strlen($rate))) . $rate;
        $amount = str_repeat(' ', max(0, $amtW - mb_strlen($amount))) . $amount;

        return $name . $qty . $rate . $amount;
    }
}
