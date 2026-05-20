{{-- 
    PARTIAL: Stat cards untuk header Sweet Mates.
    
    Variable:
      $counts → ['all' => int, 'bronze' => int, 'silver' => int, 'gold' => int]
--}}

<div class="sm-stat-grid">
    <div class="sm-stat-card">
        <div class="sm-stat-card__label">
            <i class="fa fa-users" style="margin-right:4px;"></i> Total Member
        </div>
        <div class="sm-stat-card__value">{{ $counts['all'] }}</div>
    </div>
    
    <div class="sm-stat-card">
        <div class="sm-stat-card__label">
            <span class="sm-tier-badge sm-tier-badge--bronze">
                <i class="fas fa-medal"></i> Bronze
            </span>
        </div>
        <div class="sm-stat-card__value">{{ $counts['bronze'] }}</div>
    </div>
    
    <div class="sm-stat-card">
        <div class="sm-stat-card__label">
            <span class="sm-tier-badge sm-tier-badge--silver">
                <i class="fas fa-award"></i> Silver
            </span>
        </div>
        <div class="sm-stat-card__value">{{ $counts['silver'] }}</div>
    </div>
    
    <div class="sm-stat-card">
        <div class="sm-stat-card__label">
            <span class="sm-tier-badge sm-tier-badge--gold">
                <i class="fas fa-crown"></i> Gold
            </span>
        </div>
        <div class="sm-stat-card__value">{{ $counts['gold'] }}</div>
    </div>
</div>
