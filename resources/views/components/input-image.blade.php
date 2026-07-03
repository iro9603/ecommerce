@props(['id' => 'image-preview', 'name' => 'image', 'image' => null, 'previewClass' => ''])
@php
    $inputId = $name === 'avatar' ? 'image-upload' : "{$name}-upload";
    $labelId = $name === 'avatar' ? 'image-label' : "{$name}-label";
@endphp
<div @style(["background-image: url('{$image}')" => $image])
    {{ $attributes->class(['ms-2', 'mb-2', $previewClass])->merge(['id' => $id]) }}>
    <label for="{{ $inputId }}" id="{{ $labelId }}">Choose File</label>
    <input type="file" name="{{ $name }}" id="{{ $inputId }}" accept="image/jpeg,image/png,image/webp" />
</div>
