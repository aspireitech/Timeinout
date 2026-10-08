// Home page motion: rotating headline word, live check-in feed, count-ups, reveal on scroll.
(function () {
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;

  // Rotating word in the headline
  const words = [...document.querySelectorAll('.rotator .rw')];
  let w = 0;
  if (words.length && !reduce) setInterval(() => {
    const cur = words[w];
    cur.classList.remove('on'); cur.classList.add('off');
    setTimeout(() => cur.classList.remove('off'), 500);
    w = (w + 1) % words.length;
    words[w].classList.add('on');
  }, 2400);

  // Live feed on the tablet
  const feed = document.getElementById('h-feed');
  const clock = document.getElementById('h-clock');
  const people = [
    ['NK', 'Noah Kim', 'Signed in · with Dad', 'in', 's'],
    ['RM', 'Mr. Robert Miller', 'Clocked in · Teacher', 'in', 't'],
    ['ZA', 'Zara Ahmed', 'Signed in · with Mom', 'in', 's'],
    ['EB', 'Ethan Brown', 'Picked up Homework folder', 'pk', 'p'],
    ['SN', 'Sophia Nguyen', 'Signed in · with Mom', 'in', 's'],
    ['AK', 'Ms. Aisha Khan', 'Clocked in · Teacher', 'in', 't'],
    ['OS', 'Oliver Smith', 'Signed in · with Uncle Tom', 'in', 's'],
    ['EW', 'Emma Williams', 'Signed out · with Mom', 'out', 's'],
    ['MH', 'Mateo Hernandez', 'Picked up Library books', 'pk', 'p'],
    ['AC', 'Amelia Chen', 'Signed in · with Dad', 'in', 's'],
  ];
  let minutes = 8 * 60 + 5, i = 0;
  const fmt = (m) => `${((Math.floor(m / 60) + 11) % 12) + 1}:${String(m % 60).padStart(2, '0')}`;
  if (feed && !reduce) setInterval(() => {
    minutes += 1 + Math.floor(Math.random() * 3);
    const [ini, name, sub, cls, av] = people[i++ % people.length];
    const li = document.createElement('li');
    li.innerHTML = `<span class="av av-${av}"></span><span class="fw"><b></b><small></small></span><span class="tm ${cls}"></span>`;
    li.querySelector('.av').textContent = ini;
    li.querySelector('b').textContent = name;
    li.querySelector('small').textContent = sub;
    li.querySelector('.tm').textContent = fmt(minutes);
    feed.prepend(li);
    while (feed.children.length > 5) feed.lastElementChild.remove();
    if (clock) clock.textContent = fmt(minutes) + ' AM';
  }, 2200);

  // Count-up numbers and reveal-on-scroll (only for things below the first screen)
  if (!('IntersectionObserver' in window) || reduce) return;
  const below = (el) => el.getBoundingClientRect().top > innerHeight;
  document.documentElement.classList.add('js-reveal');
  const io = new IntersectionObserver((entries) => entries.forEach((en) => {
    if (!en.isIntersecting) return;
    en.target.classList.add('shown');
    io.unobserve(en.target);
  }), { threshold: 0.15 });
  document.querySelectorAll('.reveal').forEach((el, n) => {
    if (below(el)) { el.style.transitionDelay = (n % 3) * 90 + 'ms'; io.observe(el); } else el.classList.add('shown');
  });
  // Anything already scrolled past (fast scroll, #demo link) is shown too
  const sweep = () => document.querySelectorAll('.reveal:not(.shown)').forEach((el) => {
    if (el.getBoundingClientRect().top < innerHeight) { el.classList.add('shown'); io.unobserve(el); }
  });
  addEventListener('scroll', () => requestAnimationFrame(sweep), { passive: true });
  addEventListener('hashchange', sweep);
  setTimeout(sweep, 100);
  document.querySelectorAll('[data-count]').forEach((el) => {
    const end = +el.dataset.count, suf = el.dataset.suffix || '';
    let v = 0;
    el.textContent = '0' + suf;
    const t = setInterval(() => { v = Math.min(end, v + Math.ceil(end / 20)); el.textContent = v + suf; if (v >= end) clearInterval(t); }, 45);
  });
})();
