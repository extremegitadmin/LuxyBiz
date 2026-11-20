<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Traveler Manifest - {{ $departure->tour->title ?? 'Departure' }}</title>
<style>
 body{font-family:Arial,sans-serif;color:#202b3c;margin:28px;font-size:12px}.head{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #203c5d;padding-bottom:14px;margin-bottom:18px}.head h1{font-size:21px;margin:0 0 6px}.muted{color:#667386}.meta{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:18px}.meta div{border:1px solid #dfe5ec;border-radius:7px;padding:10px}.meta small{display:block;color:#788597;margin-bottom:4px}.meta strong{font-size:13px}.summary{margin:12px 0 18px;font-weight:bold}table{width:100%;border-collapse:collapse}th{background:#edf2f7;text-align:left;padding:9px;border:1px solid #dce3eb;font-size:10px;text-transform:uppercase}td{padding:9px;border:1px solid #dce3eb;vertical-align:top}.note{max-width:260px;white-space:normal}.print{border:0;background:#173b63;color:#fff;padding:9px 14px;border-radius:6px;cursor:pointer}@media print{body{margin:12mm}.print{display:none}.head{break-after:avoid}tr{break-inside:avoid}}
</style></head>
<body>
<div class="head"><div><h1>Traveler Manifest</h1><div class="muted">LuxyBiz Tours &amp; Wellness · Operational use only</div></div><button class="print" onclick="window.print()">Print manifest</button></div>
<div class="meta">
 <div><small>Tour</small><strong>{{ $departure->tour->title ?? '—' }}</strong></div>
 <div><small>Departure date</small><strong>{{ $departure->departs_on?->format('d M Y') ?? '—' }}</strong></div>
 <div><small>Departure reference</small><strong>#{{ $departure->id }}</strong></div>
</div>
<div class="summary">Confirmed travelers: {{ $travelers->count() }}</div>
<table><thead><tr><th style="width:5%">#</th><th style="width:22%">Traveler name</th><th style="width:13%">Booking ref.</th><th style="width:13%">Nationality</th><th>Arrival details</th><th style="width:25%">Health / operational notes</th></tr></thead><tbody>
@forelse($travelers as $i => $traveler)
<tr>
 <td>{{ $i + 1 }}</td><td>{{ $traveler->full_name }} @if($traveler->is_lead)<strong>(Lead)</strong>@endif</td><td>{{ $traveler->reference }}</td><td>{{ $traveler->nationality ?: '—' }}</td>
 <td>
 @php($arrival = collect(['arrival_date','arrival_time','arrival_flight','arrival_flight_number','arrival_from','arrival_airport','arrival_notes'])->map(fn($key) => data_get($traveler, $key))->filter()->values())
 {{ $arrival->isNotEmpty() ? $arrival->implode(' · ') : 'Not provided' }}
 </td>
 <td class="note">{{ collect([$traveler->health_notes, $traveler->flag_note, isset($traveler->insurance_selected) ? ('Insurance: ' . ($traveler->insurance_selected ? 'Yes' : 'No')) : null])->filter()->implode(' · ') ?: '—' }}</td>
</tr>
@empty<tr><td colspan="6">No confirmed travelers for this departure.</td></tr>@endforelse
</tbody></table>
<p class="muted" style="margin-top:18px">Confidential traveler information. Share only with authorized fulfillment and ground operations staff.</p>
</body></html>
