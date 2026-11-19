<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Cancellations\CancellationPolicy;
use App\Support\SafeAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Cancellation rules settings (M2, Day 14). Stored in the payment settings table, read later by the refund calculation. */
class CancellationSettingsController extends Controller
{
    public function edit(Request $request)
    {
        $policy = CancellationPolicy::current();

        // "Try it": how much would be refunded N days before departure.
        $try = $request->query('try_days');
        $tryResult = is_numeric($try) && (int) $try >= 0 && (int) $try <= 3650
            ? ['days' => (int) $try, 'percent' => $policy->percentFor((int) $try)]
            : null;

        return view('admin.settings.cancellation', [
            'policy' => $policy,
            'tiers' => old('tiers', $policy->tiers),
            'bases' => CancellationPolicy::BASES,
            'tryResult' => $tryResult,
        ]);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tiers' => ['required', 'array', 'min:1', 'max:8'],
            'tiers.*.min_days' => ['nullable', 'integer', 'min:0', 'max:730'],
            'tiers.*.percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'base' => ['required', Rule::in(array_keys(CancellationPolicy::BASES))],
            'insurance_refundable' => ['nullable', 'boolean'],
            'fixed_fee' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'credit_percent' => ['required', 'integer', 'min:0', 'max:200'],
            'credit_months' => ['required', 'integer', 'min:1', 'max:60'],
            'refund_days_min' => ['required', 'integer', 'min:0', 'max:90'],
            'refund_days_max' => ['required', 'integer', 'min:0', 'max:90', 'gte:refund_days_min'],
            'confirmed' => ['nullable', 'boolean'],
        ], [
            'tiers.*.min_days.integer' => 'Days must be a whole number.',
            'tiers.*.percent.integer' => 'Refund percent must be a whole number.',
            'refund_days_max.gte' => 'The longest refund time must not be shorter than the shortest.',
        ], [
            'base' => 'refund base', 'fixed_fee' => 'fixed cancellation fee', 'credit_percent' => 'travel credit percent',
            'credit_months' => 'travel credit validity', 'refund_days_min' => 'shortest refund time', 'refund_days_max' => 'longest refund time',
        ]);

        $validator->after(function ($v) use ($request) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            $tiers = CancellationPolicy::cleanTiers((array) $request->input('tiers'));
            $submitted = collect((array) $request->input('tiers'))->filter(fn ($r) => is_array($r) && ($r['min_days'] ?? '') !== '' && ($r['percent'] ?? '') !== '')->count();

            if ($tiers === []) {
                $v->errors()->add('tiers', 'Add at least one refund tier.');

                return;
            }
            if ($submitted !== count($tiers)) {
                $v->errors()->add('tiers', 'Two tiers have the same number of days. Each row needs a different number of days.');
            }
            if (end($tiers)['min_days'] !== 0) {
                $v->errors()->add('tiers', 'The last tier must start at 0 days, so every cancellation date is covered (for example "0 days: 0%").');
            }
            // More notice must never give a smaller refund.
            for ($i = 1; $i < count($tiers); $i++) {
                if ($tiers[$i]['percent'] > $tiers[$i - 1]['percent']) {
                    $v->errors()->add('tiers', 'A shorter notice cannot refund more than a longer notice. Check the percentages.');
                    break;
                }
            }
        });

        $data = $validator->validate();

        $before = CancellationPolicy::current();
        CancellationPolicy::save([
            'tiers' => (array) $data['tiers'],
            'base' => $data['base'],
            'insurance_refundable' => $request->boolean('insurance_refundable'),
            'fixed_fee' => $data['fixed_fee'] ?? 0,
            'credit_percent' => $data['credit_percent'],
            'credit_months' => $data['credit_months'],
            'refund_days_min' => $data['refund_days_min'],
            'refund_days_max' => $data['refund_days_max'],
            'confirmed' => $request->boolean('confirmed'),
        ]);
        $after = CancellationPolicy::current();

        SafeAudit::log('settings.cancellation_rules_updated', 'Cancellation rules updated', [
            'before' => $this->flat($before),
            'after' => $this->flat($after),
        ]);

        return redirect()->route('admin.settings.cancellation')->with('success', 'Cancellation rules saved.');
    }

    private function flat(CancellationPolicy $p): array
    {
        return [
            'tiers' => $p->describeTiers(), 'base' => $p->base, 'insurance_refundable' => $p->insuranceRefundable,
            'fixed_fee' => $p->fixedFee, 'credit_percent' => $p->creditPercent, 'credit_months' => $p->creditMonths,
            'refund_days' => $p->refundDaysMin . '-' . $p->refundDaysMax, 'confirmed' => $p->confirmed,
        ];
    }
}
