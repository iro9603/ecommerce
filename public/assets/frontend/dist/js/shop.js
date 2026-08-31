(function ($) {
    'use strict';
    /*Product Details*/
    var productDetails = function () {
        $('.detail-gallery').each(function () {
            var $gallery = $(this);
            var $mainSlider = $gallery.find('.product-image-slider');
            var $thumbnailSlider = $gallery.find('.slider-nav-thumbnails');

            $mainSlider.slick({
                slidesToShow: 1,
                slidesToScroll: 1,
                arrows: false,
                fade: false,
                asNavFor: $thumbnailSlider,
            });

            $thumbnailSlider.slick({
                slidesToShow: 4,
                slidesToScroll: 1,
                asNavFor: $mainSlider,
                dots: false,
                focusOnSelect: true,

                prevArrow: '<button type="button" class="slick-prev"><i class="fi-rs-arrow-small-left"></i></button>',
                nextArrow: '<button type="button" class="slick-next"><i class="fi-rs-arrow-small-right"></i></button>'
            });

            $thumbnailSlider.find('.slick-slide').removeClass('slick-active');
            $thumbnailSlider.find('.slick-slide').eq(0).addClass('slick-active');

            $mainSlider.on('beforeChange', function (event, slick, currentSlide, nextSlide) {
                $thumbnailSlider.find('.slick-slide').removeClass('slick-active');
                $thumbnailSlider.find('.slick-slide').eq(nextSlide).addClass('slick-active');
            });

            $mainSlider.on('beforeChange', function (event, slick, currentSlide, nextSlide) {
                var img = $(slick.$slides[nextSlide]).find("img");
                $gallery.find('.zoomWindowContainer,.zoomContainer').remove();
                if ($(window).width() > 768) {
                    $(img).elevateZoom({
                        zoomType: "inner",
                        cursor: "crosshair",
                        zoomWindowFadeIn: 500,
                        zoomWindowFadeOut: 750
                    });
                }
            });

            if ($(window).width() > 768) {
                $mainSlider.find('.slick-active img').elevateZoom({
                    zoomType: "inner",
                    cursor: "crosshair",
                    zoomWindowFadeIn: 500,
                    zoomWindowFadeOut: 750
                });
            }
        });

        //Filter color/Size
        $('.list-filter').each(function () {
            $(this).find('a').on('click', function (event) {
                event.preventDefault();
                $(this).parent().siblings().removeClass('active');
                $(this).parent().addClass('active');
                $(this).parents('.attr-detail').find('.current-size').text($(this).text());
                $(this).parents('.attr-detail').find('.current-color').text($(this).attr('data-color'));
            });
        });

        //Qty Up-Down
        $('.detail-qty').each(function () {
            var qtyval = parseInt($(this).find(".qty-val").val(), 10);
            var $qtyInput = $(this).find(".qty-val");

            $(this).find('a.qty-up').on('click', function (event) {
                event.preventDefault();
                qtyval = qtyval + 1;
                $qtyInput.val(qtyval);
            });

            $(this).find('a.qty-down').on("click", function (event) {
                event.preventDefault();/*  */
                qtyval = Math.max(1, qtyval - 1);
                $qtyInput.val(qtyval);
            });
        });

        $('.dropdown-menu .cart_list').on('click', function (event) {
            event.stopPropagation();
        });
    };

    //Load functions
    $(document).ready(function () {
        productDetails();
    });

})(jQuery);
