/* ==========================================================================
   main.js — 動きは4つだけ。ライブラリは使っていない。
     1. スクロールするとヘッダーが縮んで追従する
     2. スマホでメニューを開閉する
     3. セクションが画面に入ったらふわっと出す
     4. スマホの追従CTAを、CTA帯が見えている間だけ隠す
   「動きを減らす」設定の端末では 3 を止める。
   ========================================================================== */
(function () {
  'use strict';

  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* 1. ヘッダー ---------------------------------------------------------- */
  var header = document.querySelector('[data-header]');
  if (header) {
    var onScroll = function () {
      header.classList.toggle('is-stuck', window.scrollY > 40);
    };
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
  }

  /* 2. スマホメニュー ----------------------------------------------------- */
  var toggle = document.querySelector('[data-nav-toggle]');
  var nav = document.querySelector('[data-nav]');
  if (toggle && nav) {
    var setOpen = function (open) {
      toggle.setAttribute('aria-expanded', String(open));
      nav.dataset.open = String(open);
    };
    toggle.addEventListener('click', function () {
      setOpen(toggle.getAttribute('aria-expanded') !== 'true');
    });
    nav.addEventListener('click', function (e) {
      if (e.target.closest('a')) setOpen(false);
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') setOpen(false);
    });
    window.matchMedia('(min-width: 961px)').addEventListener('change', function (m) {
      if (m.matches) setOpen(false);
    });
  }

  if (!('IntersectionObserver' in window)) {
    document.querySelectorAll('[data-reveal]').forEach(function (el) {
      el.classList.add('is-in');
    });
    return;
  }

  /* 3. スクロールで表示 --------------------------------------------------- */
  var targets = document.querySelectorAll('[data-reveal]');
  if (reduce) {
    targets.forEach(function (el) { el.classList.add('is-in'); });
  } else {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        // 並んだ要素は少しずつ時間をずらして出す
        var delay = Number(entry.target.dataset.revealDelay || 0);
        setTimeout(function () { entry.target.classList.add('is-in'); }, delay);
        io.unobserve(entry.target);
      });
    }, { rootMargin: '0px 0px -10% 0px', threshold: 0.08 });
    targets.forEach(function (el) { io.observe(el); });
  }

  /* 4. 追従CTA ------------------------------------------------------------
     CTA帯が画面に出ているあいだは、下の追従バーを隠す。
     同じ導線が二重に出るのを防ぐため。 */
  var bar = document.querySelector('[data-sticky-cta]');
  var band = document.querySelector('.cta-band');
  if (bar && band) {
    new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        bar.style.transform = entry.isIntersecting ? 'translateY(100%)' : '';
        bar.style.transition = 'transform .3s cubic-bezier(.22,.68,.31,1)';
      });
    }, { threshold: 0.15 }).observe(band);
  }
})();
