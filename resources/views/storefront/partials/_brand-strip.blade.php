@if ($brands->isNotEmpty())
<section class="sf-container" aria-label="Thương hiệu">
    <div class="sf-brand-strip">
        @foreach ($brands as $brand)
            <a class="sf-brand-tile" href="#" onclick="return false;"
               title="{{ $brand->ten }} — sắp ra mắt">
                {{ $brand->ten }}
            </a>
        @endforeach
    </div>
</section>
@endif
