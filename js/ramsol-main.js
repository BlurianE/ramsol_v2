/**
 * ==========================================================================
 * RAMSOL - SCRIPT PRINCIPAL INTERACTIVO (DISEÑO MODERNO & LIGERO)
 * ==========================================================================
 * ÍNDICE DE FUNCIONES:
 * 01. HEADER: EFECTO STICKY & BLUR AL SCROLL
 * 02. NAVEGACIÓN MÓVIL: APERTURA, CIERRE & BACKDROP DRAWER
 * 03. HERO SLIDER INTERACTIVO (AUTOPLAY, TOUCH SWIPE & BULLETS)
 * 04. REPRODUCTOR DE VIDEO FACADE (CARGA DIFERIDA DE YOUTUBE)
 * 05. ANIMACIONES AL SCROLL (INTERSECTION OBSERVER)
 * 06. BOTÓN VOLVER ARRIBA & SCROLL SUAVE
 * ==========================================================================
 */

document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  /* ==========================================================================
     01. HEADER: EFECTO STICKY & BLUR AL SCROLL
     ========================================================================== */
  const header = document.querySelector('.site-header');
  const backToTopBtn = document.querySelector('.back-to-top-btn');

  function handleScroll() {
    const scrollPos = window.scrollY || window.pageYOffset;

    // Header compacto
    if (header) {
      if (scrollPos > 40) {
        header.classList.add('scrolled');
      } else {
        header.classList.remove('scrolled');
      }
    }

    // Botón volver arriba
    if (backToTopBtn) {
      if (scrollPos > 450) {
        backToTopBtn.classList.add('visible');
      } else {
        backToTopBtn.classList.remove('visible');
      }
    }
  }

  window.addEventListener('scroll', handleScroll, { passive: true });
  handleScroll(); // Chequeo inicial

  if (backToTopBtn) {
    backToTopBtn.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  /* ==========================================================================
     02. NAVEGACIÓN MÓVIL: APERTURA, CIERRE & BACKDROP DRAWER
     ========================================================================== */
  const menuToggle = document.querySelector('.mobile-menu-toggle');
  const mobileDrawer = document.querySelector('.mobile-nav-drawer');
  const mobileBackdrop = document.querySelector('.mobile-nav-backdrop');

  function openMobileMenu() {
    if (menuToggle) menuToggle.classList.add('active');
    if (mobileDrawer) mobileDrawer.classList.add('open');
    if (mobileBackdrop) mobileBackdrop.classList.add('open');
    document.body.style.overflow = 'hidden';
  }

  function closeMobileMenu() {
    if (menuToggle) menuToggle.classList.remove('active');
    if (mobileDrawer) mobileDrawer.classList.remove('open');
    if (mobileBackdrop) mobileBackdrop.classList.remove('open');
    document.body.style.overflow = '';
  }

  if (menuToggle) {
    menuToggle.addEventListener('click', function () {
      const isOpen = mobileDrawer && mobileDrawer.classList.contains('open');
      if (isOpen) {
        closeMobileMenu();
      } else {
        openMobileMenu();
      }
    });
  }

  if (mobileBackdrop) {
    mobileBackdrop.addEventListener('click', closeMobileMenu);
  }

  // Cerrar al presionar la tecla ESC
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeMobileMenu();
  });

  // Cerrar al hacer clic en enlaces internos
  const mobileLinks = document.querySelectorAll('.mobile-nav-drawer a');
  mobileLinks.forEach(function (link) {
    link.addEventListener('click', closeMobileMenu);
  });

  /* ==========================================================================
     03. HERO SLIDER INTERACTIVO (AUTOPLAY, TOUCH SWIPE & BULLETS)
     ========================================================================== */
  const sliderContainer = document.querySelector('.hero-slider');
  const slides = document.querySelectorAll('.hero-slide');
  const dotsContainer = document.querySelector('.slider-dots');
  const prevBtn = document.querySelector('.slider-arrow.prev');
  const nextBtn = document.querySelector('.slider-arrow.next');

  if (slides.length > 0) {
    let currentSlide = 0;
    const totalSlides = slides.length;
    let autoplayTimer = null;
    const intervalTime = 6000; // 6 segundos por diapositiva

    // Crear bullets dinámicamente si el contenedor existe
    if (dotsContainer) {
      dotsContainer.innerHTML = '';
      for (let i = 0; i < totalSlides; i++) {
        const dot = document.createElement('button');
        dot.className = 'slider-dot' + (i === 0 ? ' active' : '');
        dot.setAttribute('aria-label', 'Diapositiva ' + (i + 1));
        dot.addEventListener('click', function () {
          goToSlide(i);
          resetAutoplay();
        });
        dotsContainer.appendChild(dot);
      }
    }

    const dots = document.querySelectorAll('.slider-dot');

    function goToSlide(index) {
      slides[currentSlide].classList.remove('active');
      if (dots[currentSlide]) dots[currentSlide].classList.remove('active');

      currentSlide = (index + totalSlides) % totalSlides;

      slides[currentSlide].classList.add('active');
      if (dots[currentSlide]) dots[currentSlide].classList.add('active');
    }

    function nextSlide() {
      goToSlide(currentSlide + 1);
    }

    function prevSlide() {
      goToSlide(currentSlide - 1);
    }

    if (nextBtn) {
      nextBtn.addEventListener('click', function () {
        nextSlide();
        resetAutoplay();
      });
    }

    if (prevBtn) {
      prevBtn.addEventListener('click', function () {
        prevSlide();
        resetAutoplay();
      });
    }

    // Autoplay con control de pausa al pasar el cursor
    function startAutoplay() {
      stopAutoplay();
      autoplayTimer = setInterval(nextSlide, intervalTime);
    }

    function stopAutoplay() {
      if (autoplayTimer) clearInterval(autoplayTimer);
    }

    function resetAutoplay() {
      stopAutoplay();
      startAutoplay();
    }

    if (sliderContainer) {
      sliderContainer.addEventListener('mouseenter', stopAutoplay);
      sliderContainer.addEventListener('mouseleave', startAutoplay);

      // Soporte táctil / swipe para móviles
      let touchStartX = 0;
      let touchEndX = 0;

      sliderContainer.addEventListener('touchstart', function (e) {
        touchStartX = e.changedTouches[0].screenX;
      }, { passive: true });

      sliderContainer.addEventListener('touchend', function (e) {
        touchEndX = e.changedTouches[0].screenX;
        const diffX = touchEndX - touchStartX;
        if (Math.abs(diffX) > 45) {
          if (diffX < 0) {
            nextSlide(); // Deslizar hacia la izquierda -> Siguiente
          } else {
            prevSlide(); // Deslizar hacia la derecha -> Anterior
          }
          resetAutoplay();
        }
      }, { passive: true });
    }

    // Iniciar pase de diapositivas solo si hay más de 1 diapositiva
    if (totalSlides > 1) {
      startAutoplay();
    }
  }

  /* ==========================================================================
     03B. ROTADOR DE TEXTO (MANIFIESTO / SECCIÓN 1 DEL HOME #2B2B2B)
     - Rotación continua entre las 3 frases idéntica al original Enfold
     - Crossfade puro de opacidad estacionario (sin salto vertical)
     ========================================================================== */
  const rotatorTrack = document.getElementById('manifestoRotator');
  if (rotatorTrack) {
    const items = rotatorTrack.querySelectorAll('.manifesto-rotator-item');
    if (items.length > 1) {
      let currentIndex = 0;
      let timer = null;
      const intervalMs = 4000; // 4s como en el original

      function rotateNext() {
        const prevItem = items[currentIndex];
        currentIndex = (currentIndex + 1) % items.length;
        const nextItem = items[currentIndex];

        // Fade out del texto actual
        prevItem.classList.remove('active');

        // Fade in del siguiente texto en la misma posición (crossfade)
        setTimeout(function () {
          nextItem.classList.add('active');
        }, 200);
      }

      function startTimer() {
        if (!timer) {
          timer = setInterval(rotateNext, intervalMs);
        }
      }

      function stopTimer() {
        if (timer) {
          clearInterval(timer);
          timer = null;
        }
      }

      startTimer();

      document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
          stopTimer();
        } else {
          startTimer();
        }
      });
    }
  }

  /* ==========================================================================
     03C. CARRUSEL FILTERSOL ZEROLLUVIA (SECCIÓN 2 DEL HOME)
     - Cambio fluido de fotos con autoplay cada 5s
     - Flechas de navegación, dots indicadores y soporte táctil swipe
     ========================================================================== */
  const filtersolCarousel = document.getElementById('filtersolCarousel');
  if (filtersolCarousel) {
    const fsSlides = filtersolCarousel.querySelectorAll('.filtersol-slide');
    const fsDots = filtersolCarousel.querySelectorAll('.filtersol-dot');
    const fsPrevBtn = filtersolCarousel.querySelector('.filtersol-arrow.prev');
    const fsNextBtn = filtersolCarousel.querySelector('.filtersol-arrow.next');

    if (fsSlides.length > 0) {
      let fsCurrent = 0;
      const fsTotal = fsSlides.length;
      let fsTimer = null;
      const fsInterval = 5000; // 5 segundos como en el original

      function fsGoTo(index) {
        fsSlides[fsCurrent].classList.remove('active');
        if (fsDots[fsCurrent]) fsDots[fsCurrent].classList.remove('active');

        fsCurrent = (index + fsTotal) % fsTotal;

        fsSlides[fsCurrent].classList.add('active');
        if (fsDots[fsCurrent]) fsDots[fsCurrent].classList.add('active');
      }

      function fsNext() {
        fsGoTo(fsCurrent + 1);
      }

      function fsPrev() {
        fsGoTo(fsCurrent - 1);
      }

      function fsStartAutoplay() {
        if (!fsTimer) {
          fsTimer = setInterval(fsNext, fsInterval);
        }
      }

      function fsStopAutoplay() {
        if (fsTimer) {
          clearInterval(fsTimer);
          fsTimer = null;
        }
      }

      function fsResetAutoplay() {
        fsStopAutoplay();
        fsStartAutoplay();
      }

      if (fsNextBtn) {
        fsNextBtn.addEventListener('click', function (e) {
          e.preventDefault();
          fsNext();
          fsResetAutoplay();
        });
      }

      if (fsPrevBtn) {
        fsPrevBtn.addEventListener('click', function (e) {
          e.preventDefault();
          fsPrev();
          fsResetAutoplay();
        });
      }

      fsDots.forEach(function (dot, idx) {
        dot.addEventListener('click', function (e) {
          e.preventDefault();
          fsGoTo(idx);
          fsResetAutoplay();
        });
      });

      // Pausar con hover en desktop
      filtersolCarousel.addEventListener('mouseenter', fsStopAutoplay);
      filtersolCarousel.addEventListener('mouseleave', fsStartAutoplay);

      // Soporte táctil / swipe móvil
      let fsTouchStartX = 0;
      let fsTouchEndX = 0;

      filtersolCarousel.addEventListener('touchstart', function (e) {
        fsTouchStartX = e.changedTouches[0].screenX;
      }, { passive: true });

      filtersolCarousel.addEventListener('touchend', function (e) {
        fsTouchEndX = e.changedTouches[0].screenX;
        const diffX = fsTouchEndX - fsTouchStartX;
        if (Math.abs(diffX) > 40) {
          if (diffX < 0) {
            fsNext();
          } else {
            fsPrev();
          }
          fsResetAutoplay();
        }
      }, { passive: true });

      // Iniciar autoplay
      fsStartAutoplay();

      document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
          fsStopAutoplay();
        } else {
          fsStartAutoplay();
        }
      });
    }
  }

  /* ==========================================================================
     04. REPRODUCTOR DE VIDEO FACADE (CARGA DIFERIDA DE YOUTUBE)
     ========================================================================== */
  const videoFacades = document.querySelectorAll('.video-facade-container');

  videoFacades.forEach(function (facade) {
    facade.addEventListener('click', function () {
      const videoId = facade.getAttribute('data-youtube-id');
      const startSec = facade.getAttribute('data-start') || '0';
      if (!videoId) return;

      const iframe = document.createElement('iframe');
      iframe.setAttribute('src', 'https://www.youtube.com/embed/' + videoId + '?autoplay=1&start=' + startSec + '&rel=0');
      iframe.setAttribute('title', facade.getAttribute('data-title') || 'Video Ramsol');
      iframe.setAttribute('frameborder', '0');
      iframe.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share');
      iframe.setAttribute('allowfullscreen', 'true');

      facade.innerHTML = '';
      facade.appendChild(iframe);
    });
  });

  /* ==========================================================================
     05. ANIMACIONES AL SCROLL (INTERSECTION OBSERVER)
     ========================================================================== */
  const revealElements = document.querySelectorAll('.reveal-on-scroll');

  if ('IntersectionObserver' in window && revealElements.length > 0) {
    const revealObserver = new IntersectionObserver(function (entries, observer) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-revealed');
          observer.unobserve(entry.target);
        }
      });
    }, {
      rootMargin: '0px 0px -60px 0px',
      threshold: 0.12
    });

    revealElements.forEach(function (el) {
      revealObserver.observe(el);
    });
  } else {
    // Si el navegador no soporta IntersectionObserver, mostrar directamente
    revealElements.forEach(function (el) {
      el.classList.add('is-revealed');
    });
  }

  /* ==========================================================================
     06. BOTÓN VOLVER ARRIBA & SCROLL SUAVE
     ========================================================================== */
  document.querySelectorAll('a[href^="#"]').forEach(function (anchor) {
    anchor.addEventListener('click', function (e) {
      const targetId = this.getAttribute('href');
      if (targetId && targetId !== '#' && targetId.length > 1) {
        const targetElement = document.querySelector(targetId);
        if (targetElement) {
          e.preventDefault();
          const headerOffset = 75;
          const elementPosition = targetElement.getBoundingClientRect().top;
          const offsetPosition = elementPosition + window.pageYOffset - headerOffset;

          window.scrollTo({
            top: offsetPosition,
            behavior: 'smooth'
          });
        }
      }
    });
  });

  /* ==========================================================================
     07. CARRUSELES DE GALERÍA HORIZONTAL + LIGHTBOX INTERACTIVO
     ========================================================================== */
  const carouselWraps = document.querySelectorAll('.gallery-carousel-wrap');

  carouselWraps.forEach(function (wrap) {
    const track = wrap.querySelector('.gallery-carousel-track');
    const prevBtn = wrap.querySelector('.gallery-carousel-btn.prev');
    const nextBtn = wrap.querySelector('.gallery-carousel-btn.next');
    const progressBar = wrap.querySelector('.gallery-carousel-progress-bar');

    if (!track) return;

    function updateProgress() {
      if (!progressBar) return;
      const maxScroll = track.scrollWidth - track.clientWidth;
      if (maxScroll <= 0) {
        progressBar.style.width = '100%';
        return;
      }
      const ratio = Math.min(1, Math.max(0, track.scrollLeft / maxScroll));
      const pct = 12 + ratio * 88;
      progressBar.style.width = pct.toFixed(1) + '%';
    }

    if (prevBtn) {
      prevBtn.addEventListener('click', function () {
        const step = Math.max(320, Math.round(track.clientWidth * 0.8));
        if (track.scrollLeft <= 10) {
          track.scrollTo({ left: track.scrollWidth, behavior: 'smooth' });
        } else {
          track.scrollBy({ left: -step, behavior: 'smooth' });
        }
      });
    }

    if (nextBtn) {
      nextBtn.addEventListener('click', function () {
        const step = Math.max(320, Math.round(track.clientWidth * 0.8));
        const maxScroll = track.scrollWidth - track.clientWidth;
        if (track.scrollLeft >= maxScroll - 10) {
          track.scrollTo({ left: 0, behavior: 'smooth' });
        } else {
          track.scrollBy({ left: step, behavior: 'smooth' });
        }
      });
    }

    track.addEventListener('scroll', updateProgress, { passive: true });
    window.addEventListener('resize', updateProgress, { passive: true });
    updateProgress();
  });

  const galleryContainers = document.querySelectorAll('.masonry-gallery-grid, .gallery-carousel-track');

  if (galleryContainers.length > 0) {
    const lightbox = document.createElement('div');
    lightbox.className = 'ramsol-lightbox';
    lightbox.setAttribute('role', 'dialog');
    lightbox.setAttribute('aria-modal', 'true');
    lightbox.setAttribute('aria-label', 'Visor de fotografía');
    lightbox.innerHTML =
      '<button type="button" class="ramsol-lightbox-close" aria-label="Cerrar visor"><i class="fas fa-times"></i></button>' +
      '<button type="button" class="ramsol-lightbox-nav prev" aria-label="Fotografía anterior"><i class="fas fa-chevron-left"></i></button>' +
      '<img src="" alt="Fotografía Pérgola Ramsol" class="ramsol-lightbox-img">' +
      '<button type="button" class="ramsol-lightbox-nav next" aria-label="Siguiente fotografía"><i class="fas fa-chevron-right"></i></button>' +
      '<div class="ramsol-lightbox-counter"></div>';
    document.body.appendChild(lightbox);

    const lbImg = lightbox.querySelector('.ramsol-lightbox-img');
    const lbClose = lightbox.querySelector('.ramsol-lightbox-close');
    const lbPrev = lightbox.querySelector('.ramsol-lightbox-nav.prev');
    const lbNext = lightbox.querySelector('.ramsol-lightbox-nav.next');
    const lbCounter = lightbox.querySelector('.ramsol-lightbox-counter');

    let activeSources = [];
    let activeIndex = 0;

    function renderLightboxImage() {
      if (!activeSources.length) return;
      const item = activeSources[activeIndex];
      lbImg.src = item.src;
      lbImg.alt = item.alt || 'Pérgola Ramsol';
      lbCounter.textContent = (activeIndex + 1) + ' / ' + activeSources.length;
    }

    function openLightbox(sources, index) {
      activeSources = sources;
      activeIndex = index;
      renderLightboxImage();
      lightbox.classList.add('open');
      document.body.style.overflow = 'hidden';
    }

    function closeLightbox() {
      lightbox.classList.remove('open');
      document.body.style.overflow = '';
    }

    function stepLightbox(delta) {
      if (!activeSources.length) return;
      activeIndex = (activeIndex + delta + activeSources.length) % activeSources.length;
      renderLightboxImage();
    }

    galleryContainers.forEach(function (container) {
      const items = Array.from(container.querySelectorAll('.masonry-gallery-item, .carousel-gallery-item'));
      const sources = items.map(function (el) {
        const img = el.querySelector('img');
        return {
          src: el.getAttribute('data-full') || (img ? img.getAttribute('src') : ''),
          alt: img ? img.getAttribute('alt') : ''
        };
      });

      items.forEach(function (el, idx) {
        el.addEventListener('click', function (e) {
          e.preventDefault();
          e.stopPropagation();
          openLightbox(sources, idx);
        });
      });
    });

    lbClose.addEventListener('click', closeLightbox);
    lbPrev.addEventListener('click', function (e) {
      e.stopPropagation();
      stepLightbox(-1);
    });
    lbNext.addEventListener('click', function (e) {
      e.stopPropagation();
      stepLightbox(1);
    });

    lightbox.addEventListener('click', function (e) {
      if (e.target === lightbox) closeLightbox();
    });

    document.addEventListener('keydown', function (e) {
      if (!lightbox.classList.contains('open')) return;
      if (e.key === 'Escape') closeLightbox();
      if (e.key === 'ArrowLeft') stepLightbox(-1);
      if (e.key === 'ArrowRight') stepLightbox(1);
    });
  }

  console.log('✅ Ramsol V2 - Interfaz moderna cargada con éxito.');
});