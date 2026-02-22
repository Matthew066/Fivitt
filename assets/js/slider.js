/*------------------------------------- Slider -------------------------------------*/
$(".home-slider-wrap").slick({
  slidesToShow: 2,
  slidesToScroll: 1,
  autoplay: true,
  swipeToSlide: true,
  infinite:true,
  variableWidth: true,
  autoplaySpeed: 2000,
  dots: false,
  arrows:true,
  nextArrow: '<div class="slick-custom-arrow slick-custom-arrow-right"><i class="fa-solid fa-angle-right"></i></div>',
  prevArrow: '<div class="slick-custom-arrow slick-custom-arrow-left"><i class="fa-solid fa-angle-left"></i></div>',
});

$(".beginner-wrap-bottom").slick({
  infinite: true,
  slidesToShow: 1,
  slidesToScroll: 1,
  autoplay: false,
  arrows: false,
  variableWidth: true,
  dots: false,
});

$(".single-overview-slider").slick({
  slidesToShow: 2,
  slidesToScroll: 1,
  autoplay: true,
  swipeToSlide: true,
  infinite:true,
  autoplaySpeed: 2000,
  dots: false,
  arrows:true,
  nextArrow: '<div class="slick-custom-arrow slick-custom-arrow-right"><i class="fa-solid fa-angle-right"></i></div>',
  prevArrow: '<div class="slick-custom-arrow slick-custom-arrow-left"><i class="fa-solid fa-angle-left"></i></div>',
  responsive: [
  {
    breakpoint: 400,
    settings: {
      slidesToShow: 1,
      slidesToScroll: 1
    }
  },
  {
    breakpoint: 320,
    settings: {
     slidesToShow: 1,
     slidesToScroll: 1
   }
 }
 ]
});