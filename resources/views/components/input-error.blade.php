@props(['messages'])

@if ($messages)
    @foreach ($messages as $message)
        <span {{ $attributes->merge(['class' => 'text-danger']) }}>{{ $message }}</span>
    @endforeach
@endif
