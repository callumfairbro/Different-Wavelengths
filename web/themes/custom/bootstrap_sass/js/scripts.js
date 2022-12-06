(function ($, Drupal, drupalSettings) {
    Drupal.behaviors.myBehavior = {
        attach: function (context, settings) {

          function imageModal() {
            $('.field--name-field-image').on('click', function() {
                var modal = $(this).find('.modal');
                var src = $(this).find('img').attr('src');
                $(modal).find('img').attr('src', src);
                modal.addClass('active');
                $('.modal .close').on('click', function(e) {
                    e.stopPropagation();
                    var modal = $(this).closest('.active');
                    modal.removeClass('active');
                })
            })
            $('.field--name-field-media-image').on('click', function() {
              var modal = $(this).find('.modal');
              var src = $(this).find('img').attr('src');
              $(modal).find('img').attr('src', src);
              modal.addClass('active');
              $('.modal .close').on('click', function(e) {
                  e.stopPropagation();
                  var modal = $(this).closest('.active');
                  modal.removeClass('active');
              })
          })
          }

          // Variable for menu scroll method
          var prevScrollpos = window.pageYOffset;

          function menuScroll() {
            var currentScrollPos = window.pageYOffset;
            if (prevScrollpos > currentScrollPos - 1) {
              if($('.user-logged-in').length > 0) {
                $('#navbar').css('top','79px');
              } else {
                $('#navbar').css('top', '0');
              }
            } else {
              $('#navbar').css('top', '-85px');
            }
            prevScrollpos = currentScrollPos;
          }

          function intialTopLine() {
            const lis = document.querySelectorAll(".menu--main li");
            var activeLis = document.querySelectorAll('.menu--main .active');
            if(activeLis.length == 0) {
              activeLis = document.querySelectorAll('.menu--main .first');
              activeLis[0].classList.add("active");
            }
          
            switch(activeLis[0]) {
              case lis[0]:
                document.querySelector('.line-dash').style = "stroke: orange; stroke-width: 2; stroke-linecap: round; stroke-dasharray: 180, 1200; stroke-dashoffset: -35;";
                break;
              case lis[1]:
                document.querySelector('.line-dash').style = "stroke: orange; stroke-width: 2; stroke-linecap: round; stroke-dasharray: 180, 1200; stroke-dashoffset: -285;";
                break;
              case lis[2]:
                document.querySelector('.line-dash').style = "stroke: orange; stroke-width: 2; stroke-linecap: round; stroke-dasharray: 180, 1200; stroke-dashoffset: -535;";
                break;
              case lis[3]:
                document.querySelector('.line-dash').style = "stroke: orange; stroke-width: 2; stroke-linecap: round; stroke-dasharray: 180, 1200; stroke-dashoffset: -785;";
                break;
            }
            document.querySelector('.line-dash').style.display = "unset";
          }

          function menuAnimation() {
            const lis = document.querySelectorAll(".menu--main li");
            const lbs = document.querySelectorAll(".lb");
            const ul = document.querySelector("#block-bootstrap-sass-main-menu ul");
            const lineDash = document.querySelector(".line-dash");

            // Pixels
            var dashOrigin = undefined;
            var selectedLi = undefined;

            // Move this many pixels in one second
            var speed = 500;

            var distance = 0;
            var time = 0;

            var activeLis = document.querySelectorAll('.menu--main .active');
            if(activeLis.length < 1) {
              activeLis = document.querySelectorAll('.menu--main .first');
            }

            var activeLbs = undefined;

            //Defining the origin
            for(let i = 0; i < 4; ++i) {
              if(lis[i] === activeLis[0]) {
                dashOrigin = -250 * i - 35;
                selectedLi = -250 * i - 35;
                break;
              }
            }

            // Making an item active
            for(let i = 0; i < 4; ++i) {
              if(lis[i] === activeLis[0]) {
                lis[i].classList.add('active');
                activeLbs = lbs[i];
                break;
              }
            }

            // Initial animation
            TweenLite.to(activeLbs, 0.6, {
              y: -43,
              ease: Bounce.easeOut,
              delay: 1
            });

            // Class for current page
            for(let i = 0; i < 4; ++i) {
              if(lis[i] === activeLis[0]) {
                lis[i].classList.add("active");
              }
            }

            ul.addEventListener(
              "mouseleave",
              function(e) {
                // to avoid a bug in chrome that sometimes triggers mouseleave on click
                // and the relatedTarget comes up null
                if (e.relatedTarget) {
                  distance = Math.abs(dashOrigin - selectedLi);
                  time = distance / speed;
                  dashOrigin = selectedLi;
                  if (time) {
                    // overlaping tweens would give a zero time
                    TweenLite.to(lineDash, time, {
                      strokeDashoffset: selectedLi,
                      ease: Bounce.easeOut
                    });
                  }
                }
              },
              false
            );

            for (let i = 0; i < 4; ++i) {
              lis[i].addEventListener("mouseover", function() {
                distance = Math.abs(-250 * i - 35 - dashOrigin);
                time = distance / speed;
                dashOrigin = -250 * i - 35;
                if (time) {
                  TweenLite.to(lineDash, time, {
                    strokeDashoffset: -250 * i - 35,
                    ease: Bounce.easeOut
                  });
                }
              });
            }
          }

          function addJsScroll() {
            $('.landing-page .paragraph--type--text').addClass('js-scroll');
            $('.landing-page .view-list-gig-home').addClass('js-scroll');
            $('.landing-page .view-list-media-home').addClass('js-scroll');
            $('#block-homecontact').addClass('js-scroll');
      

            $('.landing-page .paragraph--type--text').addClass('fade-in');
            $('.landing-page .view-list-gig-home').addClass('slide-left');
            $('.landing-page .view-list-media-home').addClass('slide-right');
            $('#block-homecontact').addClass('fade-in-bottom');
          }

          function homeScroll() {
 
            const scrollElements = document.querySelectorAll(".js-scroll");
            scrollElements.forEach((el) => {
              el.style.opacity = 0
            })

            const elementInView = (el, percentageScroll = 100) => {
              const elementTop = el.getBoundingClientRect().top;
              return (
                elementTop <= 
                (((window.innerHeight || document.documentElement.clientHeight) * (percentageScroll/100)) - 180)
              );
            };
            
            const displayScrollElement = (element) => {
              element.classList.add('scrolled');
            }
             
            const handleScrollAnimation = () => {
              scrollElements.forEach((el) => {
                if (elementInView(el, 100)) {
                  displayScrollElement(el);
                } 
              })
            }
            
            window.addEventListener('scroll', () => {
              handleScrollAnimation();
            })
          }
          
          function mobileMenu() {
            const nav = document.querySelector('#block-bootstrap-sass-main-menu');
            const splash = document.querySelector('.splash');
            const menu = document.querySelector('.menu--main');
            const menuToggle = document.querySelector('.navbar-toggle');
            let isMenuOpen = false;

            var listItems = document.querySelectorAll('.menu--main li');
            console.log(listItems[0]);
            for (let i = 0; i < listItems.length; ++i) {
              listItems[i].classList.add(i);
            }

            menuToggle.addEventListener('click', e => {
              e.preventDefault();
              isMenuOpen = !isMenuOpen;
              menuToggle.setAttribute('aria-expanded', String(isMenuOpen));
              menu.hidden = !isMenuOpen;
              nav.classList.toggle('open');
              splash.classList.toggle('active-splash');
            });


            nav.addEventListener('keydown', e => {
              if (!isMenuOpen || e.ctrlKey || e.metaKey || e.altKey) {
                return;
              }
              
              const menuLinks = menu.querySelectorAll('.link');
              if (e.keyCode === 9) {
                if (e.shiftKey) {
                  if (document.activeElement === menuLinks[0]) {
                    menuToggle.focus();
                    e.preventDefault();
                  }
                } else if (document.activeElement === menuToggle) {
                  menuLinks[0].focus();
                  e.preventDefault();
                }
              }
            });
          }        

          $(window).on('load', function() {
            addJsScroll();
            homeScroll();
            intialTopLine();
            menuAnimation();
            imageModal();
            mobileMenu();
          })
          
          $(window).on('scroll', function() {
            menuScroll();
          })

          $(document).ready(function() {

          })

        }
    };
})(jQuery, Drupal, drupalSettings);