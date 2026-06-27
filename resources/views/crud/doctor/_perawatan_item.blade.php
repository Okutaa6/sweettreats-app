@php $isChecked = in_array($service->id, $doctorServiceIds); @endphp
<div class="dxp-item {{ $isChecked ? 'is-on' : '' }}" data-name="{{ strtolower($service->name) }}">
    <div class="dxp-item__row">
        <span class="dxp-check" data-svc-toggle="{{ $service->id }}"><i class="fa fa-check"></i></span>
        <input type="checkbox" name="services[]" value="{{ $service->id }}" id="svc_{{ $service->id }}" data-service="{{ $service->id }}" class="dxp-cb" style="display:none;" {{ $isChecked ? 'checked' : '' }}>
        <label class="dxp-name" for="svc_{{ $service->id }}">
            <div class="dxp-name__t">{{ $service->name }}<span class="dxp-name__inline"> · default {{ $fmtDur($service->duration_minutes) }} mnt</span></div>
            <div class="dxp-name__d">Pakai durasi default</div>
        </label>
        @if($hasBranch)
            <div class="dxp-dur">
                @foreach($doctorBranches as $bi => $b)
                    @php $ov = $branchServiceDurations[$b->id][$service->id] ?? null; @endphp
                    <input type="number" name="services_duration[{{ $b->id }}][{{ $service->id }}]" class="dxp-dur-input dxp-svc-input" data-branch="{{ $b->id }}" data-service="{{ $service->id }}" min="5" step="5" placeholder="{{ $fmtDur($service->duration_minutes) }}" value="{{ $ov !== null ? $fmtDur($ov) : '' }}" style="{{ $bi === 0 ? '' : 'display:none;' }}" {{ $isChecked ? '' : 'disabled' }}>
                @endforeach
                <span>menit</span>
            </div>
        @endif
        <button type="button" class="dxp-aktifkan" data-svc-toggle="{{ $service->id }}"><i class="fa fa-plus"></i> Aktifkan</button>
    </div>

    @if($hasBranch && $service->allDetails->isNotEmpty())
        <div class="dxp-sub" data-svc-sub="{{ $service->id }}">
            <div class="dxp-sub__toggle" data-sub-toggle><i class="fa fa-chevron-right"></i> Atur durasi sub-detail ({{ $service->allDetails->count() }})</div>
            <div class="dxp-sub__list">
                @foreach($service->allDetails as $detail)
                    <div class="dxp-sub__item">
                        <span class="dxp-sub__name">{{ $detail->name }} <small>(default {{ $fmtDur($detail->duration_minutes) }} mnt)</small></span>
                        <div class="dxp-dur">
                            @foreach($doctorBranches as $bi => $b)
                                @php $dov = $branchDetailDurations[$b->id][$detail->id] ?? null; @endphp
                                <input type="number" name="details_duration[{{ $b->id }}][{{ $detail->id }}]" class="dxp-dur-input" data-branch="{{ $b->id }}" min="5" step="5" placeholder="{{ $fmtDur($detail->duration_minutes) }}" value="{{ $dov !== null ? $fmtDur($dov) : '' }}" style="{{ $bi === 0 ? '' : 'display:none;' }}">
                            @endforeach
                            <span>menit</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
