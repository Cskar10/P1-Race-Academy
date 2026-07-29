/* P1 Motion Layer (Tier 1) - front page + Online Coaching page.
   Self-contained, no build step. All effects are opt-in and bail
   gracefully on reduced-motion / no-JS / unsupported browsers. */
(function () {
  'use strict';

  var doc = document;
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var content = doc.querySelector('.single-content') || doc.querySelector('.entry-content');
  if (!content) return;
  var script = doc.currentScript;
  var assetBase = script && script.src
    ? script.src.replace(/\/[^\/?#]+(?:[?#].*)?$/, '/')
    : '/wp-content/mu-plugins/p1-motion/';

  /* ---------- 1. Scroll reveals (skip the hero so LCP is instant) ---------- */
  function setupReveals() {
    var sections = content.querySelectorAll(':scope > .wp-block-group');
    if (!sections.length) return;
    var els = [], sectionEls = [];
    sections.forEach(function (sec, idx) {
      if (idx === 0) return; // hero renders immediately
      sec.classList.add('p1-section-flow');
      sectionEls.push(sec);
      var units = sec.querySelectorAll(
        ':scope > .wp-block-heading, :scope > p, :scope > .wp-block-buttons, :scope .wp-block-columns > .wp-block-column'
      );
      units.forEach(function (u) { els.push(u); });
    });
    if (!els.length) return;

    els.forEach(function (el) {
      el.classList.add('p1-reveal');
      if (el.classList.contains('wp-block-column')) {
        var sibs = Array.prototype.slice.call(el.parentNode.children);
        el.style.transitionDelay = (sibs.indexOf(el) * 70) + 'ms';
      }
    });

    if (reduce || !('IntersectionObserver' in window)) {
      sectionEls.forEach(function (el) { el.classList.add('p1-section-in'); });
      els.forEach(function (el) { el.classList.add('p1-in'); });
      return;
    }
    var sectionIo = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) { e.target.classList.add('p1-section-in'); sectionIo.unobserve(e.target); }
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) { e.target.classList.add('p1-in'); io.unobserve(e.target); }
      });
    }, { rootMargin: '0px 0px -10% 0px', threshold: 0.12 });
    sectionEls.forEach(function (el) { sectionIo.observe(el); });
    els.forEach(function (el) { io.observe(el); });
  }

  /* ---------- 2. Hero WebGL shader (desktop only, static fallback otherwise) ---------- */
  function setupHero() {
    var hero = content.querySelector(':scope > .wp-block-group');
    if (!hero) return;
    hero.classList.add('p1-hero');
    // Hero background simplified to the plain section color (tier 1) — no WebGL track-line canvas.
  }

  /* ---------- 3. Page scroll kart ---------- */
  function setupScrollKart() {
    var kart = doc.createElement('div');
    kart.className = 'p1-scroll-kart';
    kart.setAttribute('aria-hidden', 'true');
    doc.body.appendChild(kart);

    var current = -1, target = 12, raf = 0;
    // Asset faces right; flip only when travelling left. Kart always points the way it travels.
    var faceRight = true;
    function clamp(v, min, max) { return Math.max(min, Math.min(max, v)); }
    function draw() {
      kart.style.transform = 'translate3d(' + current.toFixed(2) + 'px,0,0) scaleX(' + (faceRight ? 1 : -1) + ')';
    }
    function updateTarget() {
      var root = doc.documentElement;
      var maxScroll = Math.max(1, root.scrollHeight - window.innerHeight);
      var progress = clamp(window.scrollY / maxScroll, 0, 1);
      var width = kart.offsetWidth || 88;
      target = 12 + progress * Math.max(0, window.innerWidth - width - 24);
      if (!raf) raf = requestAnimationFrame(render);
    }
    function render() {
      raf = 0;
      if (current < 0) current = target;
      var delta = target - current;
      if (delta > 0.5) faceRight = true;        // moving right -> face forward (right)
      else if (delta < -0.5) faceRight = false; // moving left  -> face forward (left)
      current += delta * 0.18;
      draw();
      if (Math.abs(target - current) > 0.25) raf = requestAnimationFrame(render);
    }

    kart.classList.add('p1-kart-ready');
    if (reduce) {
      kart.classList.add('p1-kart-static');
      current = 12;
      draw();
      return;
    }
    updateTarget();
    window.addEventListener('scroll', updateTarget, { passive: true });
    window.addEventListener('resize', updateTarget);
  }

  /* ---------- 4. Magnetic CTAs (fine-pointer desktop only) ---------- */
  function setupMagnetic() {
    if (reduce) return;
    if (!(window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches)) return;
    var btns = content.querySelectorAll('.wp-block-button__link');
    btns.forEach(function (btn) {
      btn.classList.add('p1-magnetic');
      var strength = 0.3, max = 8;
      btn.addEventListener('pointermove', function (e) {
        var r = btn.getBoundingClientRect();
        var dx = (e.clientX - (r.left + r.width / 2)) * strength;
        var dy = (e.clientY - (r.top + r.height / 2)) * strength;
        dx = Math.max(-max, Math.min(max, dx));
        dy = Math.max(-max, Math.min(max, dy));
        btn.style.transform = 'translate(' + dx + 'px,' + dy + 'px)';
      });
      btn.addEventListener('pointerleave', function () { btn.style.transform = ''; });
    });
  }

  /* ---------- 5. Lenis smooth scroll ---------- */
  function setupLenis() {
    if (reduce || typeof Lenis === 'undefined') return;
    var lenis = new Lenis({
      duration: 1.1,
      easing: function (t) { return Math.min(1, 1.001 - Math.pow(2, -10 * t)); },
      smoothWheel: true
    });
    function raf(time) { lenis.raf(time); requestAnimationFrame(raf); }
    requestAnimationFrame(raf);

    doc.addEventListener('click', function (e) {
      var a = e.target.closest && e.target.closest('a[href^="#"]');
      if (!a) return;
      var id = a.getAttribute('href');
      if (!id || id.length < 2) return;
      var tgt = doc.querySelector(id);
      if (tgt) { e.preventDefault(); lenis.scrollTo(tgt, { offset: -20 }); }
    });
  }

  function init() { setupReveals(); setupHero(); setupScrollKart(); setupMagnetic(); setupLenis(); }
  if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', init);
  else init();
})();
