{{--
    PARTIAL: Tab nav divisi (FO / GS / Asdent) — segmented pill brown.
    Variable:
      $divisions   → Collection ChecklistDivision
      $activeDivId → string (current selected division id)
--}}
<div class="cl-tabs cl-tabs--seg">
    @foreach($divisions as $div)
        <a href="{{ route('checklist.setup', ['division' => $div->id]) }}"
           class="cl-tab {{ $activeDivId === $div->id ? 'is-active' : '' }}">
            @if($div->id === 'FO')
                <i class="fas fa-headset"></i>
            @elseif($div->id === 'GS')
                <i class="fas fa-broom"></i>
            @else
                <i class="fas fa-user-nurse"></i>
            @endif
            {{ $div->name }}
        </a>
    @endforeach
</div>
