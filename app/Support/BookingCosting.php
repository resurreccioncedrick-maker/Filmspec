<?php

namespace App\Support;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\DB;

class BookingCosting
{
    // Equipment/crew/transport rates are quoted VAT-inclusive (the "sticker price" a
    // client sees already has 12% VAT baked in — see CartController::submitBooking and
    // ce_preview.php, which both extract VAT this way for the client-facing document).
    // Given a VAT-inclusive gross amount, this returns the VAT portion embedded within it.
    private static function extractVat(float $grossInclusive): float
    {
        $rate = (float) config('filmspec.vat_rate');

        return round($grossInclusive * $rate / (1 + $rate), 2);
    }

    // Package pricing modes (Part 2c): a "package deal" price is quoted NET of VAT —
    // opposite convention from rate-card prices — with 12% added on top for the official
    // total, per standard BIR invoicing practice. Returns [subtotal, vat, grandTotal].
    private static function computeTotals(float $itemTotal, float $otherCharges, float $discountAmt, string $pricingMode, ?float $pricingInput, bool $vatExempt, float $noDiscount = 0.0): array
    {
        if ($pricingMode === 'no_discount') {
            $grand = max(0, $itemTotal + $otherCharges - $discountAmt);
            $vat = self::extractVat($grand);

            return [$grand - $vat, $vat, $grand];
        }

        // Lines flagged "no discount" sit outside the deal: the discount is worked out on
        // everything else, then they are added back at full rate on top.
        $noDiscount = max(0, min($noDiscount, $itemTotal));
        $discountable = $itemTotal - $noDiscount;

        $netBase = match ($pricingMode) {
            'package_price' => (float) $pricingInput + $noDiscount,
            'discount_percent' => $discountable * (1 - min(100, (float) $pricingInput) / 100) + $noDiscount,
            'discount_flat' => $discountable - (float) $pricingInput + $noDiscount,
            default => $itemTotal,
        };
        $netBase = max(0, $netBase);
        $rate = $vatExempt ? 0.0 : (float) config('filmspec.vat_rate');
        $vat = round($netBase * $rate, 2);

        return [$netBase, $vat, $netBase + $vat];
    }

    // Server-side transport cost — never trust a posted transport_cost value directly (the
    // browser's preview JS can go stale, get hand-edited, or fail to run at all). Always
    // re-derive it from vehicle_rates.base_rate (or the flat base_transportation_rate fallback
    // when no vehicle is selected) times the zone multiplier.
    public static function transportCost(?int $vehicleRateId, ?string $zone): float
    {
        if (!$zone) {
            return 0.0;
        }
        $zoneMultMap = ['manila' => 1.0, 'luzon' => 1.5, 'luzon_far' => 2.0];
        $mult = $zoneMultMap[$zone] ?? 1.0;
        if ($vehicleRateId) {
            $vRate = (float) (DB::table('vehicle_rates')->where('vehicle_id', $vehicleRateId)->where('is_active', 1)->value('base_rate') ?: 0);

            return round($vRate * $mult, 2);
        }
        $base = (float) (DB::table('system_settings')->where('setting_key', 'base_transportation_rate')->value('setting_value') ?: 0);

        return round($base * $mult, 2);
    }

    /**
     * The CE breakdown the Package & totals panel and the client-facing CE document render:
     * the listed buckets (FS equipment / net items / transportation / crew TF) plus the
     * equipment-vs-crew split of the discounted total.
     *
     * Nothing here is stored — it is all derived on read from columns that already exist,
     * deliberately, so there is no second copy of a number to drift out of sync.
     *
     * The split is taken FROM the CE's own net base (`subtotal`) rather than re-deriving the
     * discount, which guarantees equip_grand + crew_grand always equals the CE grand total.
     * Under a package price, crew passes through whole and the discount lands entirely on the
     * equipment side; under % / flat discount modes it is shared proportionally.
     */
    public static function breakdown(int $bookingId, ?object $ce = null): array
    {
        $ce ??= DB::table('cost_estimates')->where('booking_id', $bookingId)->orderByDesc('ce_id')->first();

        // Outsourced/partner equipment was removed as a feature — net_items stays in the
        // breakdown shape (other code still reads the key) but is always 0 now.
        $netItems = 0.0;

        if ($ce) {
            $fsEquipment = (float) $ce->equipment_total + (float) $ce->accessories_total;
            $transportation = (float) $ce->transport_total;
            $crewTf = (float) $ce->crew_total;
            $pricingMode = $ce->pricing_mode ?? 'no_discount';
            $pricingInput = $ce->pricing_input !== null ? (float) $ce->pricing_input : null;
            $vatExempt = (bool) $ce->vat_exempt;
            $packagedCost = (float) $ce->subtotal;
            $discountAmt = (float) ($ce->discount ?? 0);
        } else {
            $fsEquipment = (float) DB::table('booking_equipment')->where('booking_id', $bookingId)
                ->selectRaw('COALESCE(SUM(IF(subtotal>0,subtotal,quantity*days*daily_rate)),0) AS t')->value('t')
                + (float) DB::table('booking_accessories')->where('booking_id', $bookingId)
                    ->selectRaw('COALESCE(SUM(subtotal),0) AS t')->value('t');
            $transportation = (float) (DB::table('bookings')->where('booking_id', $bookingId)->value('transportation_cost') ?? 0);
            $crewTf = (float) DB::table('booking_crew')->where('booking_id', $bookingId)
                ->selectRaw('COALESCE(SUM(rate_used * hours_worked),0) AS t')->value('t');
            $pricingMode = 'no_discount';
            $pricingInput = null;
            $vatExempt = false;
            $packagedCost = $fsEquipment + $transportation + $netItems + $crewTf;
            $discountAmt = 0.0;
        }

        $equipSubtotal = $fsEquipment + $netItems + $transportation;
        $listedTotal = $equipSubtotal + $crewTf;
        $packaged = $pricingMode === 'no_discount' ? $listedTotal : $packagedCost;

        if ($pricingMode === 'no_discount') {
            // Rate-card prices are quoted VAT-inclusive, so VAT comes OUT of each bucket
            // rather than being added on top (vat_exempt is not honoured here, matching
            // computeTotals() — an inclusive price has the VAT baked in either way).
            //
            // A flat/percent discount approved via booking_discounts lands in $ce->discount
            // while pricing_mode stays 'no_discount' — it must still come off what's shown
            // here (computeTotals() already applies it to the booking's real total, so this
            // display would otherwise silently disagree with the actual amount owed). Split
            // the post-discount figure across equipment/crew by their listed share, same
            // proportional approach the package/percent/flat pricing-mode branch below uses.
            $discountedTotal = max(0, $listedTotal - $discountAmt);
            $f = $listedTotal > 0 ? $discountedTotal / $listedTotal : 0.0;
            $equipGross = round($equipSubtotal * $f, 2);
            $crewGross = round($discountedTotal - $equipGross, 2);
            $equipVat = self::extractVat($equipGross);
            $equipNet = $equipGross - $equipVat;
            $crewVat = self::extractVat($crewGross);
            $crewNet = $crewGross - $crewVat;
        } else {
            $rate = $vatExempt ? 0.0 : (float) config('filmspec.vat_rate');

            if ($pricingMode === 'package_price') {
                $crewNet = $crewTf;
                $equipNet = max(0, $packaged - $crewTf);
            } else {
                $f = $listedTotal > 0 ? $packaged / $listedTotal : 0.0;
                $equipNet = round($equipSubtotal * $f, 2);
                $crewNet = round($packaged - $equipNet, 2);
            }

            $equipVat = round($equipNet * $rate, 2);
            $crewVat = round($crewNet * $rate, 2);
        }

        return [
            'fs_equipment' => $fsEquipment,
            'net_items' => $netItems,
            'transportation' => $transportation,
            'equip_subtotal' => $equipSubtotal,
            'crew_tf' => $crewTf,
            'listed_total' => $listedTotal,
            'pricing_mode' => $pricingMode,
            'pricing_input' => $pricingInput,
            'vat_exempt' => $vatExempt,
            'discount_amount' => $discountAmt,
            'packaged_cost' => $packaged,
            'discount_on_packaged_cost' => max(0, $listedTotal - $packaged),
            // Outsourced/partner equipment (the only source of "no discount" lines) was
            // removed as a feature — always 0 now, key kept since other code reads it.
            'not_discounted' => 0.0,
            'equip_net' => $equipNet,
            'equip_vat' => $equipVat,
            'equip_grand' => $equipNet + $equipVat,
            'crew_net' => $crewNet,
            'crew_vat' => $crewVat,
            'crew_grand' => $crewNet + $crewVat,
            'summary_grand' => $equipNet + $equipVat + $crewNet + $crewVat,
            // True when the agreed package doesn't even cover the crew fees — the panel
            // flags this instead of rendering a negative equipment total.
            'package_under_crew' => $pricingMode === 'package_price' && $packaged < $crewTf,
        ];
    }

    // Gathers the lines a CE document/export/email needs, grouped the way the CE prints
    // (category sections, OTHERS last) — see ce_preview.php, which sections identically.
    // Shared by BookingDetailController (CSV export) and CePreviewController (booking-mode CE).
    public static function lines(int $bookingId): array
    {
        $equip = DB::table('booking_equipment as be')
            ->join('equipment as e', 'be.equipment_id', '=', 'e.equipment_id')
            ->leftJoin('equipment_categories as ec', 'e.category_id', '=', 'ec.category_id')
            ->where('be.booking_id', $bookingId)
            ->select('be.quantity', 'be.days', 'be.daily_rate', 'e.equipment_name', 'e.brand', 'ec.category_name')
            ->get();

        $groups = [];
        foreach ($equip as $line) {
            $groups[trim((string) ($line->category_name ?? '')) ?: 'Others'][] = $line;
        }
        uksort($groups, function ($a, $b) {
            $aO = strcasecmp($a, 'Others') === 0 || strcasecmp($a, 'Other') === 0;
            $bO = strcasecmp($b, 'Others') === 0 || strcasecmp($b, 'Other') === 0;
            if ($aO !== $bO) return $aO ? 1 : -1;
            return strcasecmp($a, $b);
        });

        $crew = DB::table('booking_crew as bc')
            ->join('crew_members as cm', 'bc.crew_id', '=', 'cm.crew_id')
            ->leftJoin('crew_positions as cp', 'bc.position_id', '=', 'cp.position_id')
            ->where('bc.booking_id', $bookingId)
            ->selectRaw("CONCAT(cm.first_name,' ',cm.last_name) AS crew_name, cp.position_name, bc.rate_used, bc.hours_worked")
            ->get();

        return ['groups' => $groups, 'crew' => $crew];
    }

    // Pre-discount subtotal a proposed/approved discount is computed against — shared by
    // BookingsController::proposeDiscount() and BillingController's approve/reject handlers.
    public static function rawBookingSubtotal(int $id): float
    {
        $eTotal = (float) DB::table('booking_equipment')->where('booking_id', $id)
            ->selectRaw('COALESCE(SUM(IF(subtotal>0,subtotal,quantity*days*daily_rate)),0) AS t')->value('t');
        $cTotal = (float) DB::table('booking_crew')->where('booking_id', $id)
            ->selectRaw('COALESCE(SUM(rate_used * hours_worked),0) AS t')->value('t');
        $aTotal = (float) DB::table('booking_accessories')->where('booking_id', $id)
            ->selectRaw('COALESCE(SUM(subtotal),0) AS t')->value('t');
        $transCost = (float) (DB::table('bookings')->where('booking_id', $id)->value('transportation_cost') ?? 0);

        return $eTotal + $cTotal + $aTotal + $transCost;
    }

    // Normalizes a discount type string and validates the paired value against the booking's
    // raw (pre-discount) subtotal. Returns an error message, or null when valid. Shared by
    // proposeDiscount(), setDiscount() and approveDiscount() so all three apply exactly the
    // same rules regardless of entry point (client request, staff propose, staff direct-set).
    private static function validateDiscountInput(string $type, float $value, float $rawSub): ?string
    {
        if ($value <= 0) {
            return $type === 'package'
                ? 'Enter a package price greater than zero.'
                : 'Enter a discount amount or percentage greater than zero.';
        }
        if ($type === 'percent' && $value > 100) {
            return 'Percentage discount cannot exceed 100%.';
        }
        if ($type === 'package' && $value >= $rawSub) {
            return 'Package price must be less than the itemized total (₱' . number_format($rawSub, 2) . ').';
        }

        return null;
    }

    // Peso amount actually taken off, for a given discount type/value against the booking's
    // raw subtotal — flat is the value itself, percent is a share of the subtotal, and package
    // is a target total (so the "discount" is simply the gap between the subtotal and it).
    private static function computeDiscountAmount(string $type, float $value, float $rawSub): float
    {
        $computed = match ($type) {
            'percent' => round($rawSub * $value / 100, 2),
            'package' => round(max(0, $rawSub - $value), 2),
            default => $value,
        };

        return min($computed, $rawSub);
    }

    private static function normalizeDiscountType(string $type): string
    {
        return in_array($type, ['percent', 'package'], true) ? $type : 'flat';
    }

    private static function describeDiscount(string $type, float $value): string
    {
        return match ($type) {
            'percent' => "{$value}%",
            'package' => 'a ₱' . number_format($value, 2) . ' package price',
            default => '₱' . number_format($value, 2),
        };
    }

    // Shared by BookingsController (staff, from the Bookings page), ClientBookingDetailController
    // (client, from their own booking page), and BookingDetailController (staff, directly from
    // the booking page) — who proposed it is recorded via $proposerId and resolved to "staff"
    // vs "client" later by joining booking_discounts.proposed_by -> users -> roles, the same
    // authorship pattern already used for comments (BookingDetailController.php's comment feed).
    public static function proposeDiscount(int $bookingId, int $proposerId, string $type, float $value, string $reason): array
    {
        $type = self::normalizeDiscountType($type);

        if (! $bookingId || ! DB::table('bookings')->where('booking_id', $bookingId)->exists()) {
            return ['type' => 'danger', 'text' => 'Booking not found.'];
        }
        $rawSub = self::rawBookingSubtotal($bookingId);
        if ($error = self::validateDiscountInput($type, $value, $rawSub)) {
            return ['type' => 'danger', 'text' => $error];
        }
        if (DB::table('booking_discounts')->where('booking_id', $bookingId)->where('status', 'pending')->exists()) {
            return ['type' => 'danger', 'text' => 'A discount proposal is already pending approval for this booking.'];
        }

        $computed = self::computeDiscountAmount($type, $value, $rawSub);

        DB::table('booking_discounts')->insert([
            'booking_id' => $bookingId, 'discount_type' => $type, 'discount_value' => $value,
            'computed_amount' => $computed, 'reason' => $reason ?: null, 'status' => 'pending',
            'proposed_by' => $proposerId, 'created_at' => now(),
        ]);
        $desc = self::describeDiscount($type, $value);
        ActivityLog::record($proposerId, 'propose', 'discount', "Proposed $desc discount for booking $bookingId", $bookingId);

        return ['type' => 'success', 'text' => 'Discount proposal submitted for approval.'];
    }

    public static function updateBookingTotal(int $id): void
    {
        $eTotal = (float) DB::table('booking_equipment')->where('booking_id', $id)
            ->selectRaw('COALESCE(SUM(IF(subtotal>0,subtotal,quantity*days*daily_rate)),0) AS t')->value('t');
        $cTotal = (float) DB::table('booking_crew')->where('booking_id', $id)
            ->selectRaw('COALESCE(SUM(rate_used * hours_worked),0) AS t')->value('t');
        $aTotal = (float) DB::table('booking_accessories')->where('booking_id', $id)
            ->selectRaw('COALESCE(SUM(subtotal),0) AS t')->value('t');
        $incCharge = (float) DB::table('incident_reports')->where('booking_id', $id)->where('status', '!=', 'closed')
            ->selectRaw('COALESCE(SUM(charge_amount),0) AS t')->value('t');
        $transCost = (float) (DB::table('bookings')->where('booking_id', $id)->value('transportation_cost') ?? 0);
        $sub = $eTotal + $cTotal + $aTotal + $incCharge + $transCost;

        $ceAdj = DB::table('cost_estimates')->where('booking_id', $id)->orderByDesc('ce_id')
            ->select('discount', 'other_charges', 'pricing_mode', 'pricing_input', 'vat_exempt')->first();
        $discountAmt = $ceAdj ? (float) $ceAdj->discount : 0;
        $otherCharges = $ceAdj ? (float) $ceAdj->other_charges : 0;
        $pricingMode = $ceAdj ? ($ceAdj->pricing_mode ?? 'no_discount') : 'no_discount';
        $pricingInput = $ceAdj && $ceAdj->pricing_input !== null ? (float) $ceAdj->pricing_input : null;
        $vatExempt = $ceAdj ? (bool) $ceAdj->vat_exempt : false;
        [$net, $vat, $grossIncl] = self::computeTotals($sub, $otherCharges, $discountAmt, $pricingMode, $pricingInput, $vatExempt);

        DB::table('bookings')->where('booking_id', $id)->update([
            'total_amount' => $net, 'vat_amount' => $vat, 'final_amount' => $grossIncl, 'updated_at' => now(),
        ]);
    }

    // New base references are standardized to CE-{year}-#### (4-digit, sequential per calendar
    // year), matching the panelist revision's "standardize CE numbering" requirement. A new
    // draft spun up after a prior CE was confirmed keeps that booking's base reference with a
    // revision suffix (-R2, -R3, ...) so a booking's CE history stays visually linked —
    // CE-2026-0001-R1, -R2, etc.
    //
    // NN is MAX+1 rather than COUNT+1 so deleting a CE can never hand out a number twice.
    // Pre-existing legacy references (YY-MM-NN or CE-YYYY-####) are left alone — some are
    // already out with clients — and the formats coexist without any display code caring.
    private static function nextCeReference(int $bookingId): string
    {
        $existing = DB::table('cost_estimates')->where('booking_id', $bookingId)
            ->orderBy('ce_id')->value('ce_reference');

        if ($existing) {
            $base = preg_replace('/-R\d+$/', '', $existing);
            $count = DB::table('cost_estimates')->where('booking_id', $bookingId)->count();

            return $base . '-R' . ($count + 1);
        }

        $prefix = 'CE-' . date('Y');
        $maxSeq = 0;
        $refs = DB::table('cost_estimates')->where('ce_reference', 'like', $prefix . '-%')
            ->pluck('ce_reference');
        foreach ($refs as $ref) {
            if (preg_match('/^CE-\d{4}-(\d+)/', $ref, $m)) {
                $maxSeq = max($maxSeq, (int) $m[1]);
            }
        }

        return $prefix . '-' . str_pad((string) ($maxSeq + 1), 4, '0', STR_PAD_LEFT);
    }

    public static function generateCostEstimate(int $id, int $userId): void
    {
        $eTotal = (float) DB::table('booking_equipment')->where('booking_id', $id)
            ->selectRaw('COALESCE(SUM(IF(subtotal>0,subtotal,quantity*days*daily_rate)),0) AS t')->value('t');
        $cTotal = (float) DB::table('booking_crew')->where('booking_id', $id)
            ->selectRaw('COALESCE(SUM(rate_used * hours_worked),0) AS t')->value('t');
        $aTotal = (float) DB::table('booking_accessories')->where('booking_id', $id)
            ->selectRaw('COALESCE(SUM(subtotal),0) AS t')->value('t');
        // Outsourced/partner equipment was removed as a feature — outsourced_total stays a
        // real column (other code still reads it) but is always written as 0 now.
        $oTotal = 0.0;
        $transCost = (float) (DB::table('bookings')->where('booking_id', $id)->value('transportation_cost') ?? 0);
        $rawSub = $eTotal + $cTotal + $aTotal + $oTotal + $transCost;

        $latest = DB::table('cost_estimates')->where('booking_id', $id)->orderByDesc('ce_id')->first();
        $discountAmt = $latest ? (float) $latest->discount : 0;
        $otherCharges = $latest ? (float) $latest->other_charges : 0;
        if (! $latest) {
            $discPct = (float) (DB::table('bookings as b')->join('clients as c', 'b.client_id', '=', 'c.client_id')
                ->where('b.booking_id', $id)->value('c.discount_pct') ?? 0);
            $discountAmt = round($rawSub * $discPct / 100, 2);
        }

        $pricingMode = $latest ? ($latest->pricing_mode ?? 'no_discount') : 'no_discount';
        $pricingInput = $latest && $latest->pricing_input !== null ? (float) $latest->pricing_input : null;
        $vatExempt = $latest ? (bool) $latest->vat_exempt : false;
        [$sub, $vat, $grand] = self::computeTotals($rawSub, $otherCharges, $discountAmt, $pricingMode, $pricingInput, $vatExempt);

        // A confirmed CE is locked/immutable history — further changes start a fresh draft
        // rather than overwriting it. Otherwise (no CE yet, or the latest is still a draft)
        // keep today's behavior: update that one row in place.
        $updateExisting = $latest && $latest->status !== 'confirmed';

        if ($updateExisting) {
            DB::table('cost_estimates')->where('ce_id', $latest->ce_id)->update([
                'equipment_total' => $eTotal, 'crew_total' => $cTotal, 'accessories_total' => $aTotal,
                'outsourced_total' => $oTotal,
                'transport_total' => $transCost, 'subtotal' => $sub, 'vat_amount' => $vat, 'grand_total' => $grand,
                'status' => 'draft', 'generated_at' => now(),
            ]);
        } else {
            DB::table('cost_estimates')->insert([
                'booking_id' => $id, 'ce_reference' => self::nextCeReference($id), 'generated_by' => $userId,
                'equipment_total' => $eTotal, 'crew_total' => $cTotal, 'accessories_total' => $aTotal,
                'outsourced_total' => $oTotal,
                'transport_total' => $transCost, 'discount' => $discountAmt, 'other_charges' => $otherCharges,
                'pricing_mode' => $pricingMode, 'pricing_input' => $pricingInput, 'vat_exempt' => $vatExempt,
                'subtotal' => $sub, 'vat_amount' => $vat, 'grand_total' => $grand, 'status' => 'draft',
            ]);
        }

        self::updateBookingTotal($id);
    }

    // Confirming a CE is the one moment a booking can end up with two rows both saying
    // "confirmed" (this booking already had one confirmed, then got revised and re-confirmed).
    // Demoting every other confirmed row for the same booking to 'superseded' here — in the same
    // action that creates the new confirmed row — is what CeAnalytics::onlyLatestConfirmed()'s
    // dedup filter used to have to work around; this makes 'superseded' a real, stored fact
    // instead of something inferred at query time.
    public static function confirmCe(int $bookingId, int $userId, ?string $note = null): array
    {
        $latest = DB::table('cost_estimates')->where('booking_id', $bookingId)->orderByDesc('ce_id')->first();
        if (! $latest) {
            return ['type' => 'danger', 'text' => 'No cost estimate to confirm — generate one first.'];
        }
        if ($latest->status === 'confirmed') {
            return ['type' => 'danger', 'text' => 'This cost estimate is already confirmed.'];
        }

        $now = now();
        DB::table('cost_estimates')->where('ce_id', $latest->ce_id)->update([
            'status' => 'confirmed', 'confirmed_by' => $userId, 'confirmed_at' => $now,
            'confirmation_note' => $note !== null && trim($note) !== '' ? trim($note) : null,
        ]);
        DB::table('cost_estimates')->where('booking_id', $bookingId)
            ->where('ce_id', '!=', $latest->ce_id)->where('status', 'confirmed')
            ->update(['status' => 'superseded', 'superseded_at' => $now]);

        $msg = 'Cost estimate <strong>' . e($latest->ce_reference) . '</strong> confirmed.';

        // Timeline validation: a normal CE shouldn't be confirmed after its shoot has already
        // happened — flagged here rather than blocked outright, since legitimate paperwork
        // (recording a historical/migrated CE) can legitimately trail the shoot date.
        $shootEnd = DB::table('bookings')->where('booking_id', $bookingId)->value('shoot_date_end');
        if ($shootEnd && strtotime($shootEnd) < strtotime('today')) {
            $msg .= ' <strong>Note:</strong> the shoot date has already passed — confirming this as a historical/migration record.';
        }

        return ['type' => 'success', 'text' => $msg];
    }

    // Marks the latest draft as 'issued' (Issued / Awaiting Confirmation) — an optional
    // pipeline step for when a quotation has gone out to the client but isn't confirmed yet.
    // Purely additive: Confirm CE still works directly from 'draft', this doesn't gate it.
    public static function issueCe(int $bookingId): array
    {
        $latest = DB::table('cost_estimates')->where('booking_id', $bookingId)->orderByDesc('ce_id')->first();
        if (! $latest || $latest->status !== 'draft') {
            return ['type' => 'danger', 'text' => 'Only a draft cost estimate can be marked as issued.'];
        }

        DB::table('cost_estimates')->where('ce_id', $latest->ce_id)->update(['status' => 'issued']);

        return ['type' => 'success', 'text' => 'Cost estimate <strong>' . e($latest->ce_reference) . '</strong> marked as issued — awaiting confirmation.'];
    }

    // Applies a new pricing mode to the booking's current CE. Caller is responsible for
    // validating $pricingMode/$pricingInput first (see BookingDetailController::updateCePricing).
    public static function applyPricingMode(int $bookingId, int $userId, string $pricingMode, ?float $pricingInput, bool $vatExempt): void
    {
        // Ensures a mutable draft row exists — auto-spins a new one if the latest CE was
        // already confirmed, so confirmed history is never rewritten.
        self::generateCostEstimate($bookingId, $userId);
        $latest = DB::table('cost_estimates')->where('booking_id', $bookingId)->orderByDesc('ce_id')->first();

        DB::table('cost_estimates')->where('ce_id', $latest->ce_id)->update([
            'pricing_mode' => $pricingMode, 'pricing_input' => $pricingInput, 'vat_exempt' => $vatExempt,
        ]);

        // Recompute totals now that the new pricing mode is in place.
        self::generateCostEstimate($bookingId, $userId);
    }

    // Writes an approved discount amount onto the booking's current CE. Mirrors
    // applyPricingMode()'s shape: generateCostEstimate() first guarantees a mutable draft
    // exists (spins a new one if the latest CE was already confirmed), then only THAT row's
    // ce_id is updated — never a blanket update-by-booking_id, which previously clobbered
    // the discount column on old, confirmed (supposedly immutable) CE rows too.
    public static function applyDiscount(int $bookingId, int $userId, float $discountAmt): void
    {
        self::generateCostEstimate($bookingId, $userId);
        $latest = DB::table('cost_estimates')->where('booking_id', $bookingId)->orderByDesc('ce_id')->first();

        DB::table('cost_estimates')->where('ce_id', $latest->ce_id)->update(['discount' => $discountAmt]);

        self::updateBookingTotal($bookingId);
    }

    public static function approveDiscount(int $bookingId, int $discId, int $userId, ?string $notes): array
    {
        $prop = DB::table('booking_discounts')->where('discount_id', $discId)->where('booking_id', $bookingId)->where('status', 'pending')->first();
        if (! $prop) {
            return ['type' => 'danger', 'text' => 'Discount proposal not found or already reviewed.'];
        }

        $rawSub = self::rawBookingSubtotal($bookingId);
        $computed = self::computeDiscountAmount($prop->discount_type, (float) $prop->discount_value, $rawSub);

        DB::table('booking_discounts')->where('discount_id', $discId)->update([
            'status' => 'approved', 'computed_amount' => $computed,
            'approved_by' => $userId, 'approved_at' => now(), 'review_notes' => $notes ?: null,
        ]);

        self::applyDiscount($bookingId, $userId, $computed);

        $costApprovalStatus = DB::table('bookings')->where('booking_id', $bookingId)->value('cost_approval_status');
        if ($costApprovalStatus === 'client_approved') {
            DB::table('bookings')->where('booking_id', $bookingId)->update(['cost_approval_status' => 'pending_client', 'updated_at' => now()]);
        }

        ActivityLog::record($userId, 'approve', 'discount', 'Approved ₱' . number_format($computed, 2) . " discount for booking $bookingId", $bookingId);

        return ['type' => 'success', 'text' => 'Discount of <strong>₱' . number_format($computed, 2) . '</strong> approved and applied.'];
    }

    public static function rejectDiscount(int $bookingId, int $discId, int $userId, ?string $notes): array
    {
        DB::table('booking_discounts')->where('discount_id', $discId)->where('booking_id', $bookingId)->where('status', 'pending')->update([
            'status' => 'rejected', 'approved_by' => $userId, 'approved_at' => now(), 'review_notes' => $notes ?: null,
        ]);
        ActivityLog::record($userId, 'reject', 'discount', "Rejected discount proposal for booking $bookingId", $bookingId);

        return ['type' => 'success', 'text' => 'Discount proposal rejected.'];
    }

    // For when a client calls in and staff agree a discount on the spot — no separate
    // approval step needed, since the phone call itself is the authorization.
    // Records a booking_discounts row that's already 'approved' (proposed_by and approved_by
    // both the acting staff member) and applies it immediately.
    public static function setDiscount(int $bookingId, int $staffId, string $type, float $value, string $reason): array
    {
        $type = self::normalizeDiscountType($type);

        if (! $bookingId || ! DB::table('bookings')->where('booking_id', $bookingId)->exists()) {
            return ['type' => 'danger', 'text' => 'Booking not found.'];
        }
        $rawSub = self::rawBookingSubtotal($bookingId);
        if ($error = self::validateDiscountInput($type, $value, $rawSub)) {
            return ['type' => 'danger', 'text' => $error];
        }
        if (DB::table('booking_discounts')->where('booking_id', $bookingId)->where('status', 'pending')->exists()) {
            return ['type' => 'danger', 'text' => 'A discount proposal is already pending approval for this booking — resolve it first.'];
        }

        $computed = self::computeDiscountAmount($type, $value, $rawSub);

        DB::table('booking_discounts')->insert([
            'booking_id' => $bookingId, 'discount_type' => $type, 'discount_value' => $value,
            'computed_amount' => $computed, 'reason' => $reason ?: null, 'status' => 'approved',
            'proposed_by' => $staffId, 'approved_by' => $staffId, 'approved_at' => now(), 'created_at' => now(),
        ]);

        self::applyDiscount($bookingId, $staffId, $computed);

        $costApprovalStatus = DB::table('bookings')->where('booking_id', $bookingId)->value('cost_approval_status');
        if ($costApprovalStatus === 'client_approved') {
            DB::table('bookings')->where('booking_id', $bookingId)->update(['cost_approval_status' => 'pending_client', 'updated_at' => now()]);
        }

        $desc = self::describeDiscount($type, $value);
        ActivityLog::record($staffId, 'set', 'discount', "Set $desc discount (₱" . number_format($computed, 2) . ' off) for booking ' . $bookingId, $bookingId);

        return ['type' => 'success', 'text' => 'Discount of <strong>₱' . number_format($computed, 2) . '</strong> set and applied.'];
    }
}
