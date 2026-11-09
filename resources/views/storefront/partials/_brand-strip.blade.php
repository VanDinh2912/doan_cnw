@php
    $brands = ['Apple', 'Samsung', 'Xiaomi', 'Oppo', 'Vivo', 'Realme', 'Honor', 'Nokia'];
@endphp

<section class="sf-container" aria-label="Thương hiệu">
    <div class="sf-brand-strip">
        @foreach ($brands as $brand)
            <a class="sf-brand-tile" href="#" onclick="return false;"
               title="{{ $brand }} — sắp ra mắt">
                {{ $brand }}
            </a>
        @endforeach
    </div>
</section>