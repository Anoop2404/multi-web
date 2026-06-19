// $(document).ready(function() {
//     $("#toggle-menu").click(function() {
//         if (window.innerWidth <= 768) {
//             $("#menu").slideToggle();
//         }
//     });


// });

// Define a function to handle the hover effect
function setupHoverEffect() {
    $("#menu li.has-dropdown").hover(
        function() {
            $(this).find(".sub-menu").slideDown();
        },
        function() {
            $(this).find(".sub-menu").slideUp();
        }
    );
}

// Check the viewport width and conditionally execute the function
function checkViewportWidth() {
    if (window.innerWidth >= 992) {
        setupHoverEffect();
    }
}

// Run the function on page load and when the window resizes
checkViewportWidth();
window.addEventListener('resize', checkViewportWidth);

$(document).ready(function() {
    // Toggle the 'active' class on click for small screens
    $("#menu li.has-dropdown").on("click", function() {
        if (window.innerWidth <= 992) {
            $(this).toggleClass("active");
        }
    });
});

/***************slider-banner******* */

// HERO SLIDER
var menu = [];
jQuery('.swiper-slide').each(function(index) {
    menu.push(jQuery(this).find('.slide-inner').attr("data-text"));
});
var interleaveOffset = 0.5;
var swiperOptions = {
    loop: true,
    speed: 1500,
    parallax: true,
    navigation: false,
    dots: false,
    autoplay:true,

    autoplay: {
        delay: 2500,
        disableOnInteraction: false,
        autoplay:false,
    },
    watchSlidesProgress: true,
    pagination: {
        el: '.swiper-pagination',
        clickable: true,
    },

    on: {
        progress: function() {
            var swiper = this;
            for (var i = 0; i < swiper.slides.length; i++) {
                var slideProgress = swiper.slides[i].progress;
                var innerOffset = swiper.width * interleaveOffset;
                var innerTranslate = slideProgress * innerOffset;
                swiper.slides[i].querySelector(".slide-inner").style.transform =
                    "translate3d(" + innerTranslate + "px, 0, 0)";
            }
        },

        touchStart: function() {
            var swiper = this;
            for (var i = 0; i < swiper.slides.length; i++) {
                swiper.slides[i].style.transition = "";
            }
        },

        setTransition: function(speed) {
            var swiper = this;
            for (var i = 0; i < swiper.slides.length; i++) {
                swiper.slides[i].style.transition = speed + "ms";
                swiper.slides[i].querySelector(".slide-inner").style.transition =
                    speed + "ms";
            }
        }
    }
};

var swiper = new Swiper(".swiper-container", swiperOptions);

// DATA BACKGROUND IMAGE
var sliderBgSetting = $(".slide-bg-image");
sliderBgSetting.each(function(indx) {
    if ($(this).attr("data-background")) {
        $(this).css("background-image", "url(" + $(this).data("background") + ")");
    }
});
/********************* */

// gsap.to(".mission", {
//     backgroundPosition: `0% ${-innerHeight / 4}px`,
//     ease: "none",
//     scrollTrigger: {
//         trigger: ".mission",
//         scrub: true,

//     },
// });

const boxes = gsap.utils.toArray('section');

boxes.forEach((box, i) => {
    const anim = gsap.fromTo(box, { autoAlpha: 0, y: 50 }, { duration: 3, autoAlpha: 1, y: 0 });
    ScrollTrigger.create({
        trigger: box,
        animation: anim,
        toggleActions: 'play none reverse none',
        once: true,
    });
});

var hasParallax = gsap.utils.toArray('.has-parallax');
hasParallax.forEach(function(hParallax) {
    var bgImage = hParallax.querySelector("img");
    var bgVideo = hParallax.querySelector("video");
    var parallax = gsap.fromTo([bgImage, bgVideo], { y: '-20%', scale: 1.15 }, { y: '20%', scale: 1, duration: 1, ease: Linear.easeNone });
    var parallaxScene = ScrollTrigger.create({
        trigger: hParallax,
        start: "top 100%",
        end: () => `+=${hParallax.offsetHeight + window.innerHeight}`,
        animation: parallax,
        scrub: true
    });
});

var hasParallax = gsap.utils.toArray('.bg-prod');
hasParallax.forEach(function(hParallax) {
    var bgImage = hParallax.querySelector("img");
    var bgVideo = hParallax.querySelector("video");
    var parallax = gsap.fromTo([bgImage, bgVideo], { y: '0%', scale: 1.15 }, { y: '0%', scale: 1, duration: 1, ease: Linear.easeNone });
    var parallaxScene = ScrollTrigger.create({
        trigger: hParallax,
        start: "top 100%",
        end: () => `+=${hParallax.offsetHeight + window.innerHeight}`,
        animation: parallax,
        scrub: true
    });
});


// var hasParallax = gsap.utils.toArray('section');
// hasParallax.forEach(function(hParallax) {
//     var bgImage = hParallax.querySelector(".container");
//     var bgVideo = hParallax.querySelector("video");
//     var parallax = gsap.fromTo([bgImage, bgVideo], { y: '0%', scale: .8 }, { y: '0%', scale: 1, duration: 1, ease: Linear.easeNone });
//     var parallaxScene = ScrollTrigger.create({
//         trigger: hParallax,
//         start: "top 150%",
//         end: () => `+=${hParallax.offsetHeight + window.innerHeight}`,
//         animation: parallax,
//         scrub: true
//     });
// });

AOS.init({
    duration: 1200,
})


/******************* */

$(document).ready(function() {
    $(".hamburger").click(function() {
        $(this).toggleClass("is-active");
        $("#menu").toggleClass("active");
    });
});

$(window).scroll(function() {
    if (window.innerWidth >= 992) {
        if ($(this).scrollTop() > 1) {
            $('header').addClass("sticky");
        } else {
            $('header').removeClass("sticky");
        }
    }
});

/************************ */


  var swiper = new Swiper('.swiper-service', {
    slidesPerView: 3,
    spaceBetween: 20,
    centeredSlides: false,

    loop: false,
    freeMode: false,
    initialSlide: 1, // Start centered if needed
     pauseOnMouseEnter: true,
         disableOnInteraction: false,
    navigation: {
        nextEl: '.swiper-button-next',
        prevEl: '.swiper-button-prev',
      },
        autoplay:false,

    // autoplay: {
    //     delay: 3000,
    //     disableOnInteraction: false,
    // },
      breakpoints: {
        // when window width is >= 1024px
        1300: {
          slidesPerView: 3,
        },
        // when window width is >= 768px
         768 : {
          slidesPerView: 2,
        },
         600 : {
          slidesPerView:1.1,
        },
        // when window width is < 768px
        0: {
          slidesPerView: 1.1,
        }
      }
   

  });
   
        
  var swiper = new Swiper('.swiper-news', {
    slidesPerView: 2,
    spaceBetween: 20,
    centeredSlides: false,
    loop: false,
    loop: true,
    freeMode: false,

    navigation: {
        nextEl: '.swiper-button-next',
        prevEl: '.swiper-button-prev',
      },
      pagination: {
        el: '.swiper-pagination',
        clickable: true,
      },
      breakpoints: {
        // when window width is >= 1024px
        1200: {
          slidesPerView: 2,
        },
         992: {
          slidesPerView: 2,
        },
        // when window width is >= 768px
        768: {
          slidesPerView: 1.2,
        },
        // when window width is < 768px
        0: {
          slidesPerView: 1.2,
        }
      }
  });


         
  var swiper = new Swiper('.swiper-news-new', {
    slidesPerView: 3,
    spaceBetween: 20,
    centeredSlides: true,
    loop: false,
    loop: true,
    freeMode: false,
 autoplay:true,

    autoplay: {
        delay: 2500,
        disableOnInteraction: false,
        autoplay:false,
    },
    navigation: {
        nextEl: '.swiper-button-next',
        prevEl: '.swiper-button-prev',
      },
      pagination: {
        el: '.swiper-pagination',
        clickable: true,
      },
      breakpoints: {
        // when window width is >= 1024px
        1200: {
          slidesPerView: 3,
        },
         992: {
          slidesPerView: 2,
        },
        // when window width is >= 768px
        768: {
          slidesPerView: 1.1,
        },
        // when window width is < 768px
        0: {
          slidesPerView: 1.1,
        }
      }
  });


  // Tabs Swiper
  const tabsSwiper = new Swiper('.tabs-swiper', {
    slidesPerView: 'auto',
    spaceBetween: 0,
    freeMode: true,
    slidesPerView: 3,
    navigation: {
        nextEl: '.swiper-button-next',
        prevEl: '.swiper-button-prev',
      },
     breakpoints: {
        // when window width is >= 1024px
        1300: {
          slidesPerView: 3,
        },
        // when window width is >= 768px
         768 : {
          slidesPerView: 3,
        },
         600 : {
          slidesPerView:2.4,
        },
        // when window width is < 768px
        0: {
          slidesPerView: 2.4,
        }
      }
  });

// Main content swiper
const contentSwiper = new Swiper('.content-swiper', {

  parallax: true, // enable parallax
  on: {
    slideChange: () => {
      const activeIndex = contentSwiper.activeIndex;

      document.querySelectorAll('.tab-button').forEach((el, i) => {
        el.classList.toggle('tab-button-active', i === activeIndex);
      });

      tabsSwiper.slideTo(activeIndex);
    }
  }
});

// Tab click → slide content swiper
document.querySelectorAll('.tab-button').forEach((tab, index) => {
  tab.addEventListener('click', () => {
    contentSwiper.slideTo(index);
  });
});

// Inner swipers (optional, if you want parallax inside them too)
document.querySelectorAll('.inner-swiper').forEach((swiperEl) => {
  new Swiper(swiperEl, {
    slidesPerView: 2,
    spaceBetween: 25,
    loop: true,
    nested: true,
    touchStartPreventDefault: false,
    parallax: true,
    breakpoints: {
        // when window width is >= 1024px
        1300: {
          slidesPerView: 2,
        },
        // when window width is >= 768px
         768 : {
          slidesPerView: 2,
        },
         600 : {
          slidesPerView:1.1,
        },
        // when window width is < 768px
        0: {
          slidesPerView: 1.1,
        }
      }
  });
});
/*********************** */

    const parallax = document.getElementById("parallax-bg");
  const section = document.querySelector(".counter-section");

  window.addEventListener("scroll", () => {
    const rect = section.getBoundingClientRect();
    const scrollY = window.scrollY;
    const offsetTop = section.offsetTop;
    const speed = 0.2; // Less movement = smoother
    const yOffset = (scrollY - offsetTop) * speed;

    // Shift relative to center position (-50%)
    parallax.style.transform = `translateY(calc(-50% + ${yOffset}px))`;
  });

  // Scroll-triggered counter
  function animateCounter(id, duration = 2000) {
    const el = document.getElementById(id);
    const target = parseInt(el.getAttribute('data-value')) || 0;
    let current = 0;
    const stepTime = Math.max(10, Math.floor(duration / target));
    const timer = setInterval(() => {
      current++;
      el.textContent = current;
      if (current >= target) clearInterval(timer);
    }, stepTime);
  }

  let triggered = false;

  window.addEventListener('scroll', () => {
    const rect = section.getBoundingClientRect();
    if (!triggered && rect.top < window.innerHeight) {
      animateCounter("count1");
      animateCounter("count2");
      animateCounter("count3");
      animateCounter("count4");
      triggered = true;
    }
  });