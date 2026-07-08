@if(($fields['vice_principal_name']['enabled'] ?? false) || ($fields['vice_principal_email']['enabled'] ?? false) || ($fields['vice_principal_phone']['enabled'] ?? false))
<div class="field-span-2 {{ ($fields['principal_name']['enabled'] ?? false) ? 'pt-2' : '' }}">
    <p class="text-sm font-semibold text-slate-800">Vice Principal</p>
</div>
@endif

@if($fields['vice_principal_name']['enabled'] ?? false)
<div>
    <label class="portal-label" for="vice_principal_name">{{ $fields['vice_principal_name']['label'] }}
        @if($fields['vice_principal_name']['required'])<span class="portal-required">*</span>@endif
    </label>
    <input id="vice_principal_name" name="vice_principal_name" type="text"
           value="{{ old('vice_principal_name') }}"
           class="portal-input @error('vice_principal_name') is-error @enderror"
           placeholder="{{ $fields['vice_principal_name']['placeholder'] }}"
           @if($fields['vice_principal_name']['required']) required @endif>
    @error('vice_principal_name')<p class="portal-error">{{ $message }}</p>@enderror
</div>
@endif

@if($fields['vice_principal_email']['enabled'] ?? false)
<div>
    <label class="portal-label" for="vice_principal_email">{{ $fields['vice_principal_email']['label'] }}
        @if($fields['vice_principal_email']['required'])<span class="portal-required">*</span>@endif
    </label>
    <input id="vice_principal_email" name="vice_principal_email" type="email"
           value="{{ old('vice_principal_email') }}"
           class="portal-input @error('vice_principal_email') is-error @enderror"
           placeholder="{{ $fields['vice_principal_email']['placeholder'] }}"
           @if($fields['vice_principal_email']['required']) required @endif>
    @error('vice_principal_email')<p class="portal-error">{{ $message }}</p>@enderror
</div>
@endif

@if($fields['vice_principal_phone']['enabled'] ?? false)
<div>
    <label class="portal-label" for="vice_principal_phone">{{ $fields['vice_principal_phone']['label'] }}
        @if($fields['vice_principal_phone']['required'])<span class="portal-required">*</span>@endif
    </label>
    <input id="vice_principal_phone" name="vice_principal_phone" type="tel"
           value="{{ old('vice_principal_phone') }}"
           class="portal-input @error('vice_principal_phone') is-error @enderror"
           placeholder="{{ $fields['vice_principal_phone']['placeholder'] }}"
           @if($fields['vice_principal_phone']['required']) required @endif>
    @error('vice_principal_phone')<p class="portal-error">{{ $message }}</p>@enderror
</div>
@endif

@if(($fields['event_coordinator_name']['enabled'] ?? false) || ($fields['event_coordinator_email']['enabled'] ?? false) || ($fields['event_coordinator_phone']['enabled'] ?? false))
<div class="field-span-2 pt-2">
    <p class="text-sm font-semibold text-slate-800">Events Coordinator</p>
</div>
@endif

@if($fields['event_coordinator_name']['enabled'] ?? false)
<div>
    <label class="portal-label" for="event_coordinator_name">{{ $fields['event_coordinator_name']['label'] }}
        @if($fields['event_coordinator_name']['required'])<span class="portal-required">*</span>@endif
    </label>
    <input id="event_coordinator_name" name="event_coordinator_name" type="text"
           value="{{ old('event_coordinator_name') }}"
           class="portal-input @error('event_coordinator_name') is-error @enderror"
           placeholder="{{ $fields['event_coordinator_name']['placeholder'] }}"
           @if($fields['event_coordinator_name']['required']) required @endif>
    @error('event_coordinator_name')<p class="portal-error">{{ $message }}</p>@enderror
</div>
@endif

@if($fields['event_coordinator_email']['enabled'] ?? false)
<div>
    <label class="portal-label" for="event_coordinator_email">{{ $fields['event_coordinator_email']['label'] }}
        @if($fields['event_coordinator_email']['required'])<span class="portal-required">*</span>@endif
    </label>
    <input id="event_coordinator_email" name="event_coordinator_email" type="email"
           value="{{ old('event_coordinator_email') }}"
           class="portal-input @error('event_coordinator_email') is-error @enderror"
           placeholder="{{ $fields['event_coordinator_email']['placeholder'] }}"
           @if($fields['event_coordinator_email']['required']) required @endif>
    @error('event_coordinator_email')<p class="portal-error">{{ $message }}</p>@enderror
</div>
@endif

@if($fields['event_coordinator_phone']['enabled'] ?? false)
<div>
    <label class="portal-label" for="event_coordinator_phone">{{ $fields['event_coordinator_phone']['label'] }}
        @if($fields['event_coordinator_phone']['required'])<span class="portal-required">*</span>@endif
    </label>
    <input id="event_coordinator_phone" name="event_coordinator_phone" type="tel"
           value="{{ old('event_coordinator_phone') }}"
           class="portal-input @error('event_coordinator_phone') is-error @enderror"
           placeholder="{{ $fields['event_coordinator_phone']['placeholder'] }}"
           @if($fields['event_coordinator_phone']['required']) required @endif>
    @error('event_coordinator_phone')<p class="portal-error">{{ $message }}</p>@enderror
</div>
@endif
