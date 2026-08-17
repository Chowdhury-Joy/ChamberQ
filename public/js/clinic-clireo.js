/*
 * Clinic-tier homepage motion (Clireo/CBPH reference, approved 2026-08-06).
 * Ported from public/previews/clireo-homepage.html on 2026-08-07.
 *
 * Section-by-section reveal, Framer-like word blur on headings, mobile drawer,
 * treatments carousel, Voices of relief auto-scroll, and stat counters. Every effect is skipped under
 * prefers-reduced-motion; the page must be fully readable with JS off, so
 * nothing here may be the only thing that makes content visible.
 */
(function () {

  function syncClinicNavHeight() {
    var nav = document.querySelector('.nav');
    if (!nav) return;
    document.documentElement.style.setProperty(
      '--clinic-nav-height',
      Math.ceil(nav.getBoundingClientRect().height) + 'px'
    );
  }
  syncClinicNavHeight();
  window.addEventListener('resize', syncClinicNavHeight);

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, function (ch) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch];
    });
  }

  // Framer-like word blur reveal
  function splitWords(el, emWords) {
    if (!el || el.dataset.fxSplit === '1') return;
    // Never split a wrapper that contains nested headings/blocks
    if (el.querySelector('h1,h2,h3,h4,p,a,button,ul,ol,section,div')) return;
    emWords = emWords || [];
    var text = el.textContent.replace(/\s+/g, ' ').trim();
    var parts = text.split(' ');
    el.innerHTML = parts.map(function (w, i) {
      var clean = w.replace(/[^a-zA-Z0-9+]/g, '');
      var isEm = emWords.some(function (e) { return e.toLowerCase() === clean.toLowerCase(); });
      var cls = 'fx-word' + (isEm ? ' fx-em' : '');
      return '<span class="' + cls + '">' + escapeHtml(w) + '</span>' + (i < parts.length - 1 ? ' ' : '');
    }).join('');
    el.dataset.fxSplit = '1';
  }

  document.querySelectorAll('h1[data-fx-words], h2[data-fx-words], h3[data-fx-words], .fx-heading[data-fx-words]').forEach(function (el) {
    var em = (el.getAttribute('data-fx-em') || '').split(',').map(function (s) { return s.trim(); }).filter(Boolean);
    splitWords(el, em);
  });

  function revealWords(root, baseDelay, onDone, timing) {
    baseDelay = baseDelay || 0;
    timing = timing || { interval: 70, settle: 700 };
    var words = root.querySelectorAll ? root.querySelectorAll('.fx-word') : [];
    if (!words.length) {
      if (onDone) onDone();
      return;
    }
    words.forEach(function (w, i) {
      setTimeout(function () { w.classList.add('is-in'); }, baseDelay + i * timing.interval);
    });
    if (onDone) {
      var doneAt = baseDelay + (words.length - 1) * timing.interval + timing.settle;
      setTimeout(onDone, doneAt);
    }
  }

  function wordRevealTiming() {
    return { interval: 24, baseDelay: 16, settle: 320 };
  }

  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var SECTION_STEP_MS = 200;
  var HERO_STEP_MS = 160;
  var STAGGER_MS = 40;
  var activeRevealSection = null;
  var counted = false;

  function stepMs() {
    if (activeRevealSection && activeRevealSection.classList.contains('hero')) {
      return HERO_STEP_MS;
    }
    return SECTION_STEP_MS;
  }

  function staggerMs() {
    if (activeRevealSection && activeRevealSection.classList.contains('hero')) {
      return 50;
    }
    return STAGGER_MS;
  }

  function runCounters() {
    if (counted) return;
    counted = true;
    document.querySelectorAll('[data-count]').forEach(function (el) {
      var target = parseInt(el.getAttribute('data-count'), 10) || 0;
      var steps = 40;
      var i = 0;
      var timer = setInterval(function () {
        i += 1;
        el.textContent = Math.round((target * i) / steps);
        if (i >= steps) clearInterval(timer);
      }, 30);
    });
  }

  function revealFadeElement(el, onDone) {
    if (!el) { if (onDone) onDone(); return; }
    function finish() {
      el.classList.add('is-in');
      if (el.classList.contains('stats-band')) runCounters();
      if (onDone) setTimeout(onDone, reduceMotion ? 0 : stepMs());
    }
    if (el.classList.contains('treat-stage') && typeof window.__prepareTreatStage === 'function' && !reduceMotion) {
      window.__prepareTreatStage(finish);
      return;
    }
    finish();
  }

  function revealHeading(el, onDone) {
    if (!el) { if (onDone) onDone(); return; }
    if (reduceMotion) {
      el.querySelectorAll('.fx-word').forEach(function (w) { w.classList.add('is-in'); });
      if (onDone) onDone();
      return;
    }
    var timing = wordRevealTiming();
    revealWords(el, timing.baseDelay, onDone, timing);
  }

  function revealStagger(step, onDone) {
    if (!step) { if (onDone) onDone(); return; }
    step.classList.add('is-in');
    var selector = step.getAttribute('data-reveal-stagger') || ':scope > *';
    var items = step.querySelectorAll(selector);
    if (!items.length) { if (onDone) onDone(); return; }
    if (reduceMotion) {
      items.forEach(function (item) { item.classList.add('is-in'); });
      if (onDone) onDone();
      return;
    }
    var i = 0;
    function revealNext() {
      if (i >= items.length) {
        if (onDone) setTimeout(onDone, stepMs());
        return;
      }
      items[i].classList.add('is-in');
      i += 1;
      setTimeout(revealNext, staggerMs());
    }
    revealNext();
  }

  function getRevealBlocks(section) {
    var blocks = Array.prototype.slice.call(section.querySelectorAll('[data-reveal-block]'));
    return blocks.sort(function (a, b) {
      var pa = parseInt(a.getAttribute('data-reveal-priority') || '2', 10);
      var pb = parseInt(b.getAttribute('data-reveal-priority') || '2', 10);
      if (pa !== pb) return pa - pb;
      var rectA = a.getBoundingClientRect();
      var rectB = b.getBoundingClientRect();
      var topA = rectA.top + window.scrollY;
      var topB = rectB.top + window.scrollY;
      if (Math.abs(topA - topB) < 12) {
        return rectA.left - rectB.left;
      }
      return topA - topB;
    });
  }

  function revealBlock(block, onDone) {
    var kind = block.getAttribute('data-reveal-kind') || 'fade';
    if (kind === 'heading') {
      var heading = block.matches('h1,h2,h3') ? block : block.querySelector('h1.fx-heading, h2.fx-heading, h3.fx-heading');
      revealHeading(heading || block, onDone);
    } else if (kind === 'stagger') {
      revealStagger(block, onDone);
    } else {
      revealFadeElement(block, onDone);
    }
  }

  function runSectionSequence(section) {
    if (section.dataset.revealDone === '1') return;
    section.dataset.revealDone = '1';
    activeRevealSection = section;
    var blocks = getRevealBlocks(section);
    if (!blocks.length) return;
    if (reduceMotion) {
      blocks.forEach(function (block) {
        var kind = block.getAttribute('data-reveal-kind') || 'fade';
        if (kind === 'heading') {
          var heading = block.matches('h1,h2,h3') ? block : block.querySelector('h1.fx-heading, h2.fx-heading, h3.fx-heading');
          if (heading) heading.querySelectorAll('.fx-word').forEach(function (w) { w.classList.add('is-in'); });
        } else if (kind === 'stagger') {
          block.classList.add('is-in');
          var selector = block.getAttribute('data-reveal-stagger') || ':scope > *';
          block.querySelectorAll(selector).forEach(function (item) { item.classList.add('is-in'); });
        } else {
          block.classList.add('is-in');
          if (block.classList.contains('stats-band')) runCounters();
        }
      });
      return;
    }
    var idx = 0;
    function next() {
      if (idx >= blocks.length) return;
      revealBlock(blocks[idx++], next);
    }
    next();
  }

  function initSectionReveals() {
    var sections = document.querySelectorAll('[data-reveal-section]');
    if (!sections.length) return;
    var heroDelay = reduceMotion ? 0 : 480;
    var sio = null;

    function bindSection(section) {
      if (section.classList.contains('hero')) {
        setTimeout(function () { runSectionSequence(section); }, heroDelay);
        return;
      }
      if (reduceMotion || !('IntersectionObserver' in window)) {
        runSectionSequence(section);
        return;
      }
      sio.observe(section);
    }

    if (!reduceMotion && 'IntersectionObserver' in window) {
      sio = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (!entry.isIntersecting) return;
          runSectionSequence(entry.target);
          sio.unobserve(entry.target);
        });
      }, { threshold: 0.12, rootMargin: '0px 0px -48px 0px' });
    }

    sections.forEach(bindSection);
  }

  initSectionReveals();

  var drawer = document.querySelector('[data-drawer]');
  var openBtn = document.querySelector('[data-open-menu]');
  function setOpen(open) {
    if (!drawer || !openBtn) return;
    drawer.classList.toggle('open', open);
    drawer.setAttribute('aria-hidden', open ? 'false' : 'true');
  }
  if (openBtn) openBtn.addEventListener('click', function () { setOpen(true); });
  document.querySelectorAll('[data-close-menu]').forEach(function (el) {
    el.addEventListener('click', function () { setOpen(false); });
  });

  var scroller = document.querySelector('[data-treat-scroll]');
  var prev = document.querySelector('[data-treat-prev]');
  var next = document.querySelector('[data-treat-next]');
  if (scroller && prev && next) {
    var treatDesktop = window.matchMedia('(min-width: 1200px)');
    var originalCards = Array.prototype.slice.call(
      scroller.querySelectorAll('.treat-card:not([data-treat-clone])')
    );
    var originalCount = originalCards.length;
    var isJumping = false;
    var loopReady = false;
    var scrollEndTimer;

    function ensureTreatClones() {
      if (scroller.dataset.treatLooped === '1' || originalCount < 1) return;
      originalCards.slice().reverse().forEach(function (card) {
        var clone = card.cloneNode(true);
        clone.setAttribute('data-treat-clone', 'prepend');
        clone.setAttribute('aria-hidden', 'true');
        clone.setAttribute('tabindex', '-1');
        clone.classList.remove('reveal');
        scroller.insertBefore(clone, scroller.firstChild);
      });
      originalCards.forEach(function (card) {
        var clone = card.cloneNode(true);
        clone.setAttribute('data-treat-clone', 'append');
        clone.setAttribute('aria-hidden', 'true');
        clone.setAttribute('tabindex', '-1');
        clone.classList.remove('reveal');
        scroller.appendChild(clone);
      });
      originalCards.forEach(function (card) {
        card.setAttribute('data-treat-original', '1');
      });
      scroller.dataset.treatLooped = '1';
    }

    function treatCards() {
      return scroller.querySelectorAll('.treat-card');
    }

    function treatStep() {
      var cards = treatCards();
      var card = cards[0];
      if (!card) return 320;
      var gap = parseFloat(getComputedStyle(scroller).columnGap || getComputedStyle(scroller).gap) || 24;
      return card.getBoundingClientRect().width + gap;
    }

    function originalBlockWidth() {
      return originalCount * treatStep();
    }

    function updateTreatDim() {
      var cards = treatCards();
      if (!treatDesktop.matches) {
        cards.forEach(function (c) { c.classList.remove('is-dim'); });
        return;
      }
      var bounds = scroller.getBoundingClientRect();
      var visible = [];
      cards.forEach(function (card) {
        var rect = card.getBoundingClientRect();
        var visibleLeft = Math.max(rect.left, bounds.left);
        var visibleRight = Math.min(rect.right, bounds.right);
        var visibleWidth = Math.max(0, visibleRight - visibleLeft);
        var ratio = rect.width > 0 ? visibleWidth / rect.width : 0;
        if (ratio > 0.12) {
          visible.push({ card: card, left: rect.left, ratio: ratio });
        }
      });
      visible.sort(function (a, b) { return a.left - b.left; });
      cards.forEach(function (c) { c.classList.remove('is-dim'); });
      visible.forEach(function (entry, index) {
        var isEdge = index === 0 || index === visible.length - 1;
        var isClipped = entry.ratio < 0.92;
        if (isEdge || isClipped) {
          entry.card.classList.add('is-dim');
        }
      });
    }

    function checkTreatLoop() {
      if (!treatDesktop.matches || !loopReady || isJumping || originalCount < 1) return;
      var block = originalBlockWidth();
      if (block < 2) return;
      var sl = scroller.scrollLeft;
      if (sl >= block * 2 - 4) {
        isJumping = true;
        scroller.scrollLeft = sl - block;
        requestAnimationFrame(function () {
          isJumping = false;
          updateTreatDim();
        });
      } else if (sl <= 4) {
        isJumping = true;
        scroller.scrollLeft = sl + block;
        requestAnimationFrame(function () {
          isJumping = false;
          updateTreatDim();
        });
      }
    }

    function scheduleLoopCheck() {
      clearTimeout(scrollEndTimer);
      scrollEndTimer = setTimeout(function () {
        checkTreatLoop();
        updateTreatDim();
      }, 140);
    }

    function initTreatDesktopScroll(onReady) {
      if (!treatDesktop.matches || originalCount < 1) {
        if (onReady) onReady();
        return;
      }
      ensureTreatClones();
      loopReady = false;
      var stage = scroller.closest('.treat-stage');
      if (stage) stage.classList.remove('is-positioned');
      requestAnimationFrame(function () {
        var step = treatStep();
        var block = originalBlockWidth();
        if (step < 2 || block < 2) {
          if (stage) stage.classList.add('is-positioned');
          if (onReady) onReady();
          return;
        }
        scroller.scrollLeft = block - step;
        loopReady = true;
        updateTreatDim();
        if (stage) stage.classList.add('is-positioned');
        if (onReady) onReady();
      });
    }

    window.__prepareTreatStage = function (done) {
      initTreatDesktopScroll(done || function () {});
    };

    function resetTreatMobileScroll() {
      loopReady = false;
      scroller.scrollLeft = 0;
      updateTreatDim();
    }

    prev.addEventListener('click', function () {
      scroller.scrollBy({ left: -treatStep(), behavior: 'smooth' });
    });
    next.addEventListener('click', function () {
      scroller.scrollBy({ left: treatStep(), behavior: 'smooth' });
    });

    scroller.addEventListener('scroll', function () {
      if (isJumping) return;
      window.requestAnimationFrame(updateTreatDim);
      scheduleLoopCheck();
    }, { passive: true });

    scroller.addEventListener('scrollend', function () {
      checkTreatLoop();
      updateTreatDim();
    });

    window.addEventListener('resize', function () {
      window.requestAnimationFrame(function () {
        if (treatDesktop.matches) initTreatDesktopScroll();
        else resetTreatMobileScroll();
      });
    });

    treatDesktop.addEventListener('change', function () {
      if (treatDesktop.matches) initTreatDesktopScroll();
      else resetTreatMobileScroll();
    });

    if (document.readyState === 'complete') {
      requestAnimationFrame(function () { initTreatDesktopScroll(); });
    } else {
      window.addEventListener('load', function () {
        requestAnimationFrame(function () { initTreatDesktopScroll(); });
      });
    }
    updateTreatDim();
  }

  /*
   * Voices of relief (`testimonials`). The strip already swipes with no JS.
   * This adds a slow auto-advance that loops, pauses while the reader hovers
   * or focuses a card, and never runs under prefers-reduced-motion.
   */
  (function initReviewScroller() {
    var scroller = document.querySelector('[data-review-scroll]');
    if (!scroller) return;

    var originals = Array.prototype.slice.call(
      scroller.querySelectorAll('.review-card:not([data-review-clone])')
    );
    if (originals.length < 3) return;

    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var paused = false;
    var jumping = false;
    var timer = null;
    var resumeTimer = null;

    function step() {
      var card = scroller.querySelector('.review-card');
      if (!card) return 320;
      var gap = parseFloat(getComputedStyle(scroller).columnGap || getComputedStyle(scroller).gap) || 24;
      return card.getBoundingClientRect().width + gap;
    }

    function originalBlock() {
      return originals.length * step();
    }

    function overflows() {
      return originalBlock() > scroller.clientWidth + 8;
    }

    function ensureClones() {
      if (scroller.dataset.reviewLooped === '1' || !overflows()) return;
      originals.forEach(function (card) {
        var clone = card.cloneNode(true);
        clone.setAttribute('data-review-clone', '1');
        clone.setAttribute('aria-hidden', 'true');
        scroller.appendChild(clone);
      });
      scroller.dataset.reviewLooped = '1';
    }

    function wrapIfNeeded() {
      if (jumping || scroller.dataset.reviewLooped !== '1') return;
      var block = originalBlock();
      if (block < 2) return;
      if (scroller.scrollLeft >= block - 2) {
        jumping = true;
        scroller.scrollLeft = scroller.scrollLeft - block;
        requestAnimationFrame(function () { jumping = false; });
      }
    }

    function tick() {
      if (paused || document.hidden || !overflows()) return;
      scroller.scrollBy({ left: step(), behavior: 'smooth' });
    }

    function start() {
      if (reduceMotion || timer || !overflows()) return;
      timer = setInterval(tick, 4200);
    }

    function stop() {
      if (timer) {
        clearInterval(timer);
        timer = null;
      }
    }

    function pauseForReader() {
      paused = true;
      clearTimeout(resumeTimer);
    }

    function resumeSoon() {
      clearTimeout(resumeTimer);
      resumeTimer = setTimeout(function () { paused = false; }, 1800);
    }

    ensureClones();

    scroller.addEventListener('scroll', function () {
      if (jumping) return;
      wrapIfNeeded();
    }, { passive: true });
    scroller.addEventListener('scrollend', wrapIfNeeded);

    scroller.addEventListener('pointerenter', pauseForReader);
    scroller.addEventListener('pointerleave', function () { paused = false; });
    scroller.addEventListener('focusin', pauseForReader);
    scroller.addEventListener('focusout', resumeSoon);
    scroller.addEventListener('touchstart', pauseForReader, { passive: true });
    scroller.addEventListener('touchend', resumeSoon, { passive: true });
    scroller.addEventListener('wheel', function () {
      pauseForReader();
      resumeSoon();
    }, { passive: true });

    window.addEventListener('resize', function () {
      ensureClones();
      if (!overflows()) stop();
      else start();
    });

    start();
  })();

  /*
   * Image slider (`image_carousel`). Progressive enhancement only: the track
   * is a scroll-snap strip that already works by swipe with no JS, and this
   * adds arrows, dots and autoplay on top. Autoplay stops permanently on the
   * first real interaction — a carousel that keeps yanking the slide away
   * while someone is reading the caption is worse than no autoplay.
   */
  document.querySelectorAll('[data-slider]').forEach(function (slider) {
    var track = slider.querySelector('[data-slider-track]');
    if (!track) return;

    var slides = Array.prototype.slice.call(track.querySelectorAll('.slide'));
    if (slides.length < 2) return;

    var dots = Array.prototype.slice.call(slider.querySelectorAll('[data-slider-dot]'));
    var prev = slider.querySelector('[data-slider-prev]');
    var next = slider.querySelector('[data-slider-next]');
    var autoplayMs = parseInt(slider.getAttribute('data-slider-autoplay'), 10) || 0;
    var timer = null;
    var index = 0;

    function markCurrent(i) {
      index = i;
      dots.forEach(function (dot, di) {
        dot.setAttribute('aria-current', di === i ? 'true' : 'false');
      });
    }

    function goTo(i) {
      var target = (i + slides.length) % slides.length;
      track.scrollTo({ left: slides[target].offsetLeft - track.offsetLeft, behavior: 'smooth' });
      markCurrent(target);
    }

    function stopAutoplay() {
      if (timer) { clearInterval(timer); timer = null; }
    }

    if (prev) prev.addEventListener('click', function () { stopAutoplay(); goTo(index - 1); });
    if (next) next.addEventListener('click', function () { stopAutoplay(); goTo(index + 1); });
    dots.forEach(function (dot, di) {
      dot.addEventListener('click', function () { stopAutoplay(); goTo(di); });
    });
    track.addEventListener('touchstart', stopAutoplay, { passive: true });

    // Keep the dots honest when the strip is scrolled or swiped directly.
    track.addEventListener('scroll', function () {
      var nearest = 0;
      var best = Infinity;
      slides.forEach(function (slide, si) {
        var d = Math.abs(slide.offsetLeft - track.offsetLeft - track.scrollLeft);
        if (d < best) { best = d; nearest = si; }
      });
      if (nearest !== index) markCurrent(nearest);
    }, { passive: true });

    markCurrent(0);

    if (autoplayMs > 0 && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      timer = setInterval(function () {
        if (document.hidden) return;
        goTo(index + 1);
      }, autoplayMs);
    }
  });

  var reduceMotionReveal = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (reduceMotionReveal) {
    document.querySelectorAll('.reveal').forEach(function (el) { el.classList.add('is-in'); });
  } else if ('IntersectionObserver' in window) {
    var rio = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-in');
          rio.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -48px 0px' });
    document.querySelectorAll('.reveal').forEach(function (el) {
      if (el.closest('[data-reveal-section]')) return;
      rio.observe(el);
    });
  } else {
    document.querySelectorAll('.reveal').forEach(function (el) { el.classList.add('is-in'); });
  }
})();
