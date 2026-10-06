{{-- A person's avatar: their profile photo when they have one, otherwise their initials on brand green.
     Size and shape come from the caller's classes (e.g. "size-9 rounded-full text-xs"). --}}
@props(['user'])

@if ($user->profile_photo)
    <img src="{{ \Illuminate\Support\Facades\Storage::url($user->profile_photo) }}" alt="" {{ $attributes->merge(['class' => 'shrink-0 object-cover']) }}>
@else
    <span {{ $attributes->merge(['class' => 'flex shrink-0 items-center justify-center bg-brand font-semibold text-white']) }}>{{ \App\Support\Avatar::initials($user->name) }}</span>
@endif
