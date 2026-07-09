/**
 * Afro Fresh Store — Main JavaScript
 * Animations, interactions & scroll effects
 */

(function () {
  'use strict';

  // ---- Preloader ----
  const preloader = document.getElementById('preloader');

  window.addEventListener('load', () => {
    setTimeout(() => {
      preloader.classList.add('hidden');
      document.body.classList.add('loaded');
    }, 1200);
  });

  // ---- Header scroll effect ----
  const header = document.getElementById('header');

  function onScroll() {
    header.classList.toggle('scrolled', window.scrollY > 50);
  }

  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  // ---- Mobile navigation ----
  const navToggle = document.getElementById('navToggle');
  const navLinks = document.getElementById('navLinks');
  const navLinkItems = document.querySelectorAll('.nav-link');

  navToggle.addEventListener('click', () => {
    navToggle.classList.toggle('active');
    navLinks.classList.toggle('open');
    document.body.style.overflow = navLinks.classList.contains('open') ? 'hidden' : '';
  });

  navLinkItems.forEach((link) => {
    link.addEventListener('click', () => {
      navToggle.classList.remove('active');
      navLinks.classList.remove('open');
      document.body.style.overflow = '';
    });
  });

  // ---- Active nav link on scroll ----
  const sections = document.querySelectorAll('section[id]');

  function setActiveNav() {
    const scrollY = window.scrollY + 120;

    sections.forEach((section) => {
      const id = section.getAttribute('id');
      const link = document.querySelector(`.nav-link[href="#${id}"]`);
      if (!link) return;

      const top = section.offsetTop;
      const height = section.offsetHeight;

      if (scrollY >= top && scrollY < top + height) {
        navLinkItems.forEach((l) => l.classList.remove('active'));
        link.classList.add('active');
      }
    });
  }

  window.addEventListener('scroll', setActiveNav, { passive: true });

  // ---- Scroll reveal ----
  const revealElements = document.querySelectorAll('.reveal-up, .reveal-left');

  const revealObserver = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
          revealObserver.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.12, rootMargin: '0px 0px -40px 0px' }
  );

  revealElements.forEach((el) => revealObserver.observe(el));

  // ---- Counter animation ----
  const statNums = document.querySelectorAll('.stat-num[data-count]');

  const counterObserver = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;

        const el = entry.target;
        const target = parseInt(el.dataset.count, 10);
        const duration = 2000;
        const start = performance.now();

        function update(now) {
          const elapsed = now - start;
          const progress = Math.min(elapsed / duration, 1);
          const eased = 1 - Math.pow(1 - progress, 3);
          el.textContent = Math.floor(eased * target);
          if (progress < 1) requestAnimationFrame(update);
        }

        requestAnimationFrame(update);
        counterObserver.unobserve(el);
      });
    },
    { threshold: 0.5 }
  );

  statNums.forEach((el) => counterObserver.observe(el));

  // ---- Cursor glow (desktop only) ----
  const cursorGlow = document.getElementById('cursorGlow');

  if (window.matchMedia('(pointer: fine)').matches) {
    let mouseX = 0;
    let mouseY = 0;
    let glowX = 0;
    let glowY = 0;

    document.addEventListener('mousemove', (e) => {
      mouseX = e.clientX;
      mouseY = e.clientY;
    });

    function animateGlow() {
      glowX += (mouseX - glowX) * 0.08;
      glowY += (mouseY - glowY) * 0.08;
      cursorGlow.style.left = glowX + 'px';
      cursorGlow.style.top = glowY + 'px';
      requestAnimationFrame(animateGlow);
    }

    animateGlow();
  }

  // ---- Smooth anchor scroll with offset ----
  document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
    anchor.addEventListener('click', (e) => {
      const href = anchor.getAttribute('href');
      if (href === '#') return;

      const target = document.querySelector(href);
      if (!target) return;

      e.preventDefault();
      target.scrollIntoView({ behavior: 'smooth' });
    });
  });

  // ---- Parallax on hero orbs ----
  const orbs = document.querySelectorAll('.orb');

  if (orbs.length) {
    window.addEventListener('scroll', () => {
      const scrollY = window.scrollY;
      orbs.forEach((orb, i) => {
        const speed = 0.05 + i * 0.03;
        orb.style.transform = `translateY(${scrollY * speed}px)`;
      });
    }, { passive: true });
  }

  // ---- Product card tilt effect ----
  const productCards = document.querySelectorAll('.product-card');

  productCards.forEach((card) => {
    card.addEventListener('mousemove', (e) => {
      const rect = card.getBoundingClientRect();
      const x = e.clientX - rect.left;
      const y = e.clientY - rect.top;
      const centerX = rect.width / 2;
      const centerY = rect.height / 2;
      const rotateX = (y - centerY) / 20;
      const rotateY = (centerX - x) / 20;

      card.style.transform = `perspective(800px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-8px)`;
    });

    card.addEventListener('mouseleave', () => {
      card.style.transform = '';
    });
  });

})();
