// ===== GSAP + ScrollTrigger =====
gsap.registerPlugin(ScrollTrigger);

// ===== NAVBAR SCROLL =====
const navbar = document.querySelector('.navbar');
const isHomePage = !!document.querySelector('.hero');

if (navbar && isHomePage) {
  // Home: add scrolled + capsule effect on scroll
  window.addEventListener('scroll', () => {
    const scrolled = window.scrollY > 60;
    navbar.classList.toggle('scrolled', scrolled);
    navbar.classList.toggle('navbar--scrolled-active', scrolled);
  });
} else if (navbar && navbar.classList.contains('scrolled')) {
  // Subpages: navbar starts with .scrolled, activate capsule on scroll
  window.addEventListener('scroll', () => {
    navbar.classList.toggle('navbar--scrolled-active', window.scrollY > 30);
  });
}

// ===== HERO VIDEO SPEED =====
const heroVideo = document.querySelector('.hero-video');
if (heroVideo) {
  heroVideo.playbackRate = 0.6;
}

// ===== HERO ANIMATIONS (solo si existe .hero) =====
if (document.querySelector('.hero')) {
  const heroTl = gsap.timeline({
    defaults: { ease: 'power3.out' }
  });

  heroTl
    .from('.hero-tag', {
      opacity: 0,
      y: 20,
      duration: 0.7,
      delay: 0.3,
    })
    .from('.hero-title', {
      opacity: 0,
      y: 30,
      duration: 0.9,
    }, '-=0.4')
    .from('.hero-subtitle', {
      opacity: 0,
      y: 20,
      duration: 0.7,
    }, '-=0.5')
    .from('.hero-cta', {
      opacity: 0,
      y: 15,
      duration: 0.6,
    }, '-=0.4')
    .to('.hero-badge', {
      opacity: 1,
      y: 0,
      stagger: 0.1,
      duration: 0.5,
    }, '-=0.3');

  // Parallax on scroll
  gsap.to('.hero-video', {
    scrollTrigger: {
      trigger: '.hero',
      start: 'top top',
      end: 'bottom top',
      scrub: true,
    },
    scale: 1.1,
    ease: 'none',
  });

  gsap.to('.hero-content', {
    scrollTrigger: {
      trigger: '.hero',
      start: 'top top',
      end: 'bottom top',
      scrub: true,
    },
    y: -60,
    opacity: 0.3,
    ease: 'none',
  });

  gsap.to('.hero-badges', {
    scrollTrigger: {
      trigger: '.hero',
      start: 'top top',
      end: '60% top',
      scrub: true,
    },
    y: 30,
    opacity: 0,
    ease: 'none',
  });
}

// ===== NAVBAR ENTRANCE =====
if (document.querySelector('.nav-pills')) {
  gsap.set('.nav-pills', { opacity: 0, y: -15 });
  gsap.to('.nav-pills', {
    opacity: 1,
    y: 0,
    duration: 0.6,
    delay: 0.2,
    ease: 'power2.out',
  });
}

if (document.querySelector('.nav-cta')) {
  gsap.set('.nav-cta', { opacity: 0, y: -15 });
  gsap.to('.nav-cta', {
    opacity: 1,
    y: 0,
    duration: 0.6,
    delay: 0.35,
    ease: 'power2.out',
  });
}

// ===== ABOUT SECTION — scroll reveal sutil =====
if (document.querySelector('.about')) {
  gsap.set('.about-card--text', { opacity: 0, y: 40 });
  gsap.set('.about-card--img', { opacity: 0, y: 50 });

  ScrollTrigger.create({
    trigger: '.about',
    start: 'top 85%',
    once: true,
    onEnter: () => {
      gsap.to('.about-card--text', {
        opacity: 1,
        y: 0,
        duration: 0.8,
        ease: 'power3.out',
      });

      gsap.to('.about-card--img', {
        opacity: 1,
        y: 0,
        stagger: 0.15,
        duration: 0.8,
        delay: 0.2,
        ease: 'power3.out',
      });
    },
  });
}

// ===== ESPECIALIDADES — pills stagger cascade =====
if (document.querySelector('.especialidades')) {
  // Set initial state explicitly
  gsap.set('.especialidades-header', { opacity: 0, y: 30 });
  gsap.set('.pill', { opacity: 0, y: 25, scale: 0.92 });

  ScrollTrigger.create({
    trigger: '.especialidades',
    start: 'top 85%',
    once: true,
    onEnter: () => {
      gsap.to('.especialidades-header', {
        opacity: 1,
        y: 0,
        duration: 0.7,
        ease: 'power3.out',
      });

      gsap.to('.pill', {
        opacity: 1,
        y: 0,
        scale: 1,
        stagger: {
          each: 0.06,
          from: 'random',
        },
        duration: 0.5,
        delay: 0.3,
        ease: 'back.out(1.4)',
      });
    },
  });
}

// ===== PROFESIONALES — cards reveal =====
if (document.querySelector('.profesionales')) {
  gsap.set('.profesionales-header', { opacity: 0, y: 30 });
  gsap.set('.pro-card', { opacity: 0, y: 45 });

  ScrollTrigger.create({
    trigger: '.profesionales',
    start: 'top 85%',
    once: true,
    onEnter: () => {
      gsap.to('.profesionales-header', {
        opacity: 1,
        y: 0,
        duration: 0.7,
        ease: 'power3.out',
      });

      gsap.to('.pro-card', {
        opacity: 1,
        y: 0,
        stagger: 0.15,
        duration: 0.7,
        delay: 0.25,
        ease: 'power3.out',
      });
    },
  });
}

// ===== MISIÓN — Scroll-driven word reveal =====
if (document.querySelector('.mision')) {
  const words = gsap.utils.toArray('.mision-text .word');

  // Set initial state: slightly pushed down and low opacity
  gsap.set(words, {
    opacity: 0.15,
    y: 8,
    filter: 'blur(2px)',
  });

  // Create a timeline scrubbed by scroll
  const misionTl = gsap.timeline({
    scrollTrigger: {
      trigger: '.mision',
      start: 'top 75%',
      end: 'bottom 40%',
      scrub: 0.8,
    },
  });

  // Each word reveals with opacity, y, blur, and gets the is-active class
  words.forEach((word, i) => {
    misionTl.to(word, {
      opacity: 1,
      y: 0,
      filter: 'blur(0px)',
      duration: 1,
      ease: 'power2.out',
      onStart: () => word.classList.add('is-active'),
      onReverseComplete: () => word.classList.remove('is-active'),
    }, i * 0.3);
  });
}

// ===== UBICACIÓN / MAPA — reveal =====
if (document.querySelector('.ubicacion')) {
  gsap.set('.ubicacion-wrapper', { opacity: 0, y: 50 });

  ScrollTrigger.create({
    trigger: '.ubicacion',
    start: 'top 85%',
    once: true,
    onEnter: () => {
      gsap.to('.ubicacion-wrapper', {
        opacity: 1,
        y: 0,
        duration: 0.9,
        ease: 'power3.out',
      });
    },
  });
}

// ===== SERVICIOS PAGE — cards stagger =====
if (document.querySelector('.servicios')) {
  gsap.set('.servicio-card', { opacity: 0, y: 35 });

  ScrollTrigger.create({
    trigger: '.servicios',
    start: 'top 85%',
    once: true,
    onEnter: () => {
      gsap.to('.servicio-card', {
        opacity: 1,
        y: 0,
        stagger: {
          each: 0.07,
          grid: 'auto',
          from: 'start',
        },
        duration: 0.6,
        ease: 'power3.out',
      });
    },
  });
}

// ===== TELEMEDICINA — steps stagger =====
if (document.querySelector('.telemedicina')) {
  gsap.set('.telemedicina-header', { opacity: 0, y: 30 });
  gsap.set('.tele-step', { opacity: 0, y: 25 });
  gsap.set('.tele-step-connector', { opacity: 0, scale: 0.5 });
  gsap.set('.telemedicina-cta', { opacity: 0, y: 15 });

  ScrollTrigger.create({
    trigger: '.telemedicina',
    start: 'top 85%',
    once: true,
    onEnter: () => {
      gsap.to('.telemedicina-header', {
        opacity: 1,
        y: 0,
        duration: 0.7,
        ease: 'power3.out',
      });

      gsap.to('.tele-step', {
        opacity: 1,
        y: 0,
        stagger: 0.15,
        duration: 0.6,
        delay: 0.3,
        ease: 'power3.out',
      });

      gsap.to('.tele-step-connector', {
        opacity: 1,
        scale: 1,
        stagger: 0.15,
        duration: 0.4,
        delay: 0.45,
        ease: 'back.out(1.5)',
      });

      gsap.to('.telemedicina-cta', {
        opacity: 1,
        y: 0,
        duration: 0.6,
        delay: 0.9,
        ease: 'power3.out',
      });
    },
  });
}

// ===== SERVICIOS CTA — reveal =====
if (document.querySelector('.servicios-cta')) {
  gsap.set('.servicios-cta-content', { opacity: 0, y: 30 });
  gsap.set('.servicios-cta-buttons', { opacity: 0, y: 20 });

  ScrollTrigger.create({
    trigger: '.servicios-cta',
    start: 'top 85%',
    once: true,
    onEnter: () => {
      gsap.to('.servicios-cta-content', {
        opacity: 1,
        y: 0,
        duration: 0.7,
        ease: 'power3.out',
      });
      gsap.to('.servicios-cta-buttons', {
        opacity: 1,
        y: 0,
        duration: 0.6,
        delay: 0.25,
        ease: 'power3.out',
      });
    },
  });
}

// ===== PAGE HEADER — entrance =====
if (document.querySelector('.page-header')) {
  gsap.set('.page-header .section-label', { opacity: 0, y: 15 });
  gsap.set('.page-header-title', { opacity: 0, y: 25 });
  gsap.set('.page-header-subtitle', { opacity: 0, y: 15 });

  gsap.to('.page-header .section-label', {
    opacity: 1, y: 0, duration: 0.6, delay: 0.2, ease: 'power2.out',
  });
  gsap.to('.page-header-title', {
    opacity: 1, y: 0, duration: 0.7, delay: 0.35, ease: 'power2.out',
  });
  gsap.to('.page-header-subtitle', {
    opacity: 1, y: 0, duration: 0.6, delay: 0.5, ease: 'power2.out',
  });
}

// ===== QUIÉNES SOMOS — INTRO =====
if (document.querySelector('.qs-intro')) {
  gsap.set('.qs-intro-text', { opacity: 0, y: 40 });
  gsap.set('.qs-intro-img', { opacity: 0, y: 50 });

  ScrollTrigger.create({
    trigger: '.qs-intro',
    start: 'top 85%',
    once: true,
    onEnter: () => {
      gsap.to('.qs-intro-text', {
        opacity: 1,
        y: 0,
        duration: 0.8,
        ease: 'power3.out',
      });

      gsap.to('.qs-intro-img', {
        opacity: 1,
        y: 0,
        duration: 0.8,
        delay: 0.2,
        ease: 'power3.out',
      });
    },
  });
}

// ===== QUIÉNES SOMOS — FUNDADORES =====
if (document.querySelector('.qs-fundadores')) {
  gsap.set('.qs-fundadores-img', { opacity: 0, y: 50 });
  gsap.set('.qs-fundadores-text', { opacity: 0, y: 40 });

  ScrollTrigger.create({
    trigger: '.qs-fundadores',
    start: 'top 85%',
    once: true,
    onEnter: () => {
      gsap.to('.qs-fundadores-img', {
        opacity: 1,
        y: 0,
        duration: 0.8,
        ease: 'power3.out',
      });

      gsap.to('.qs-fundadores-text', {
        opacity: 1,
        y: 0,
        duration: 0.8,
        delay: 0.15,
        ease: 'power3.out',
      });
    },
  });
}

// ===== QUIÉNES SOMOS — VALORES =====
if (document.querySelector('.qs-valores')) {
  gsap.set('.qs-valores-header', { opacity: 0, y: 30 });
  gsap.set('.qs-valor-card', { opacity: 0, y: 35 });

  ScrollTrigger.create({
    trigger: '.qs-valores',
    start: 'top 85%',
    once: true,
    onEnter: () => {
      gsap.to('.qs-valores-header', {
        opacity: 1,
        y: 0,
        duration: 0.7,
        ease: 'power3.out',
      });

      gsap.to('.qs-valor-card', {
        opacity: 1,
        y: 0,
        stagger: 0.1,
        duration: 0.6,
        delay: 0.25,
        ease: 'power3.out',
      });
    },
  });
}

// ===== QUIÉNES SOMOS — INSTALACIONES =====
if (document.querySelector('.qs-instalaciones')) {
  gsap.set('.qs-instalaciones-header', { opacity: 0, y: 30 });
  gsap.set('.qs-galeria-item', { opacity: 0, y: 40, scale: 0.95 });

  ScrollTrigger.create({
    trigger: '.qs-instalaciones',
    start: 'top 85%',
    once: true,
    onEnter: () => {
      gsap.to('.qs-instalaciones-header', {
        opacity: 1,
        y: 0,
        duration: 0.7,
        ease: 'power3.out',
      });

      gsap.to('.qs-galeria-item', {
        opacity: 1,
        y: 0,
        scale: 1,
        stagger: 0.1,
        duration: 0.6,
        delay: 0.25,
        ease: 'power3.out',
      });
    },
  });
}

// ===== QUIÉNES SOMOS — CTA =====
if (document.querySelector('.qs-cta')) {
  gsap.set('.qs-cta-content', { opacity: 0, y: 30 });
  gsap.set('.qs-cta-buttons', { opacity: 0, y: 20 });

  ScrollTrigger.create({
    trigger: '.qs-cta',
    start: 'top 85%',
    once: true,
    onEnter: () => {
      gsap.to('.qs-cta-content', {
        opacity: 1,
        y: 0,
        duration: 0.7,
        ease: 'power3.out',
      });
      gsap.to('.qs-cta-buttons', {
        opacity: 1,
        y: 0,
        duration: 0.6,
        delay: 0.25,
        ease: 'power3.out',
      });
    },
  });
}

// ===== CONTACTO — form + info reveal =====
if (document.querySelector('.contacto')) {
  gsap.set('.contacto-info', { opacity: 0, y: 40 });
  gsap.set('.contacto-form-wrapper', { opacity: 0, y: 50 });

  ScrollTrigger.create({
    trigger: '.contacto',
    start: 'top 85%',
    once: true,
    onEnter: () => {
      gsap.to('.contacto-info', {
        opacity: 1,
        y: 0,
        duration: 0.8,
        ease: 'power3.out',
      });

      gsap.to('.contacto-form-wrapper', {
        opacity: 1,
        y: 0,
        duration: 0.8,
        delay: 0.15,
        ease: 'power3.out',
      });
    },
  });
}

// ===== CONTACTO — info cards reveal =====
if (document.querySelector('.contacto-cards')) {
  gsap.set('.contacto-card', { opacity: 0, y: 35 });

  ScrollTrigger.create({
    trigger: '.contacto-cards',
    start: 'top 85%',
    once: true,
    onEnter: () => {
      gsap.to('.contacto-card', {
        opacity: 1,
        y: 0,
        stagger: 0.15,
        duration: 0.7,
        ease: 'power3.out',
      });
    },
  });
}

// ===== CONTACTO — mapa reveal =====
if (document.querySelector('.contacto-mapa')) {
  gsap.set('.contacto-mapa-wrapper', { opacity: 0, y: 40 });

  ScrollTrigger.create({
    trigger: '.contacto-mapa',
    start: 'top 85%',
    once: true,
    onEnter: () => {
      gsap.to('.contacto-mapa-wrapper', {
        opacity: 1,
        y: 0,
        duration: 0.8,
        ease: 'power3.out',
      });
    },
  });
}

// ===== EQUIPO MÉDICO — search + category filter =====
window.initEquipoFeatures = function () {
  const filtros = document.querySelectorAll('.equipo-filtro');
  const docCards = document.querySelectorAll('.doc-card');
  const searchInput = document.getElementById('equipo-search-input');
  const searchClear = document.getElementById('equipo-search-clear');
  const noResults = document.getElementById('equipo-no-results');

  let activeFilter = 'todos';

  function normalize(str) {
    return str.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  }

  function applyFilters() {
    const query = normalize(searchInput ? searchInput.value.trim() : '');
    const visible = [];

    docCards.forEach((card) => {
      let cats = [];
      try { cats = JSON.parse(card.getAttribute('data-category') || '[]'); } catch(e) {}
      const name = normalize(card.querySelector('.doc-card__name')?.textContent || '');

      const matchCat = activeFilter === 'todos' || cats.includes(activeFilter);
      const matchSearch = !query || name.includes(query);

      const show = matchCat && matchSearch;
      card.style.display = show ? '' : 'none';
      if (show) visible.push(card);
    });

    if (noResults) noResults.style.display = visible.length === 0 ? 'block' : 'none';

    gsap.set(visible, { opacity: 0, y: 16 });
    gsap.to(visible, { opacity: 1, y: 0, stagger: 0.04, duration: 0.3, ease: 'power2.out' });
  }

  if (filtros.length && docCards.length) {
    filtros.forEach((btn) => {
      btn.addEventListener('click', () => {
        filtros.forEach((b) => b.classList.remove('active'));
        btn.classList.add('active');
        activeFilter = btn.getAttribute('data-filter');
        applyFilters();
      });
    });

    if (searchInput) {
      searchInput.addEventListener('input', () => {
        searchClear?.classList.toggle('visible', searchInput.value.length > 0);
        applyFilters();
      });
      searchClear?.addEventListener('click', () => {
        searchInput.value = '';
        searchClear.classList.remove('visible');
        applyFilters();
        searchInput.focus();
      });
    }

    // Activar filtro por hash en URL
    const hash = window.location.hash.replace('#', '');
    if (hash) {
      const hashBtn = document.querySelector(`.equipo-filtro[data-filter="${hash}"]`);
      if (hashBtn) {
        hashBtn.click();
        setTimeout(() => hashBtn.scrollIntoView({ behavior: 'smooth', block: 'center' }), 400);
      }
    }
  }

  // ===== EQUIPO MÉDICO — scroll animations =====
  if (document.querySelector('.equipo-filtros')) {
    gsap.set('.equipo-filtros', { opacity: 0, y: 20 });
    ScrollTrigger.create({
      trigger: '.equipo-filtros',
      start: 'top 90%',
      once: true,
      onEnter: () => {
        gsap.to('.equipo-filtros', { opacity: 1, y: 0, duration: 0.5, ease: 'power2.out' });
      },
    });
  }

  if (docCards.length) {
    gsap.set('.doc-card', { opacity: 0, y: 30 });

    ScrollTrigger.batch('.doc-card', {
      start: 'top 92%',
      once: true,
      batchMax: 3,
      onEnter: (batch) => {
        gsap.to(batch, {
          opacity: 1,
          y: 0,
          stagger: 0.08,
          duration: 0.45,
          ease: 'power2.out',
        });
      },
    });
  }

  ScrollTrigger.refresh();
};

if (document.querySelector('.equipo-cta')) {
  gsap.set('.equipo-cta-container', { opacity: 0, y: 30 });
  ScrollTrigger.create({
    trigger: '.equipo-cta',
    start: 'top 88%',
    once: true,
    onEnter: () => {
      gsap.to('.equipo-cta-container', { opacity: 1, y: 0, duration: 0.6, ease: 'power2.out' });
    },
  });
}

// ===== RESERVAR POR DOCTOR — filter + search =====
const rdFiltros = document.querySelectorAll('.rd-filtros .equipo-filtro');
const rdRows = document.querySelectorAll('.rd-row');
const rdSearchInput = document.getElementById('rd-search-input');
const rdEmpty = document.querySelector('.rd-empty');

if (rdFiltros.length && rdRows.length) {
  let activeFilter = 'todos';

  function filterDoctors() {
    const query = rdSearchInput ? rdSearchInput.value.toLowerCase().trim() : '';
    let visibleCount = 0;

    rdRows.forEach((row) => {
      const category = row.getAttribute('data-category');
      const name = row.querySelector('.rd-row__name').textContent.toLowerCase();
      const role = row.querySelector('.rd-row__role').textContent.toLowerCase();
      const matchFilter = activeFilter === 'todos' || category === activeFilter;
      const matchSearch = !query || name.includes(query) || role.includes(query);

      if (matchFilter && matchSearch) {
        row.classList.remove('hidden');
        visibleCount++;
      } else {
        row.classList.add('hidden');
      }
    });

    if (rdEmpty) {
      rdEmpty.style.display = visibleCount === 0 ? 'block' : 'none';
    }
  }

  rdFiltros.forEach((btn) => {
    btn.addEventListener('click', () => {
      rdFiltros.forEach((b) => b.classList.remove('active'));
      btn.classList.add('active');
      activeFilter = btn.getAttribute('data-filter');
      filterDoctors();
    });
  });

  if (rdSearchInput) {
    rdSearchInput.addEventListener('input', filterDoctors);
  }
}

// ===== BACK TO TOP =====
const backToTop = document.querySelector('.back-to-top');
if (backToTop) {
  window.addEventListener('scroll', () => {
    backToTop.classList.toggle('visible', window.scrollY > 400);
  });
  backToTop.addEventListener('click', () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });
}

// ===== MOBILE MENU TOGGLE =====
const menuToggle = document.querySelector('.menu-toggle');
const navPills = document.querySelector('.nav-pills');

if (menuToggle && navPills) {
  menuToggle.addEventListener('click', () => {
    const isOpen = navPills.classList.toggle('active');
    menuToggle.classList.toggle('active');
    document.body.style.overflow = isOpen ? 'hidden' : '';
  });

  // Close menu when clicking a link
  navPills.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', () => {
      navPills.classList.remove('active');
      menuToggle.classList.remove('active');
      document.body.style.overflow = '';
    });
  });
}
