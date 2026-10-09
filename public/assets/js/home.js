// Punch card: alternate clock-in and clock-out stamps for different people, in step with the card animation.
(function () {
  if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  const people = [['Maria Gomez · Front desk', 'in', 'IN 08:02'], ['Maria Gomez · Front desk', 'out', 'OUT 17:05'],
    ['Dev Patel · Warehouse A', 'in', 'IN 06:58'], ['Ava Chen · Studio 2', 'in', 'IN 09:30'], ['Dev Patel · Warehouse A', 'out', 'OUT 15:12']];
  const name = document.getElementById('punch-name'), stamp = document.getElementById('punch-stamp');
  let i = 0;
  setInterval(() => {
    i = (i + 1) % people.length;
    const [n, cls, txt] = people[i];
    name.textContent = n; stamp.textContent = txt; stamp.className = 'stamp ' + cls;
  }, 4000);
})();
