@props(['label', 'value', 'note', 'icon', 'color' => 'blue'])
<article class="stat-card"><div class="stat-top"><span>{{ $label }}</span><span class="icon-tile {{ $color }}"><x-icon :name="$icon" /></span></div><div class="stat-bottom"><strong>{{ $value }}</strong><span class="stat-note {{ $color }}">{{ $note }}</span></div></article>
