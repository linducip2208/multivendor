@props(['endsAt'])

<span data-sf-countdown="{{ is_string($endsAt) ? $endsAt : $endsAt->toIso8601String() }}" class="sf-countdown" role="timer"></span>
