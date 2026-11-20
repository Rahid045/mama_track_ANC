// Dependency-free SVG line charts for visit trends (works offline).
document.querySelectorAll('[data-chart]').forEach(el => draw(el, JSON.parse(el.dataset.chart)));
function draw(el, c) {
  const W = 480, H = 230, m = {l: 40, r: 12, t: 30, b: 28}, NS = 'http://www.w3.org/2000/svg';
  const vals = [];
  c.series.forEach(s => s.data.forEach(v => v !== null && vals.push(v)));
  if (!vals.length) { el.innerHTML = '<b>' + c.title + '</b><p class="muted">No data recorded yet.</p>'; return; }
  // keep reference lines in view only if they are near the data range
  let lo = Math.min(...vals), hi = Math.max(...vals);
  (c.refs || []).forEach(r => { if (r.y > lo - (hi - lo) * 0.6 - 5 && r.y < hi + (hi - lo) * 0.6 + 5) { lo = Math.min(lo, r.y); hi = Math.max(hi, r.y); } });
  const pad = (hi - lo || 1) * 0.15; lo = Math.floor(lo - pad); hi = Math.ceil(hi + pad);
  const n = c.labels.length, iw = W - m.l - m.r, ih = H - m.t - m.b;
  const X = i => m.l + (n === 1 ? iw / 2 : i * iw / (n - 1)), Y = v => m.t + (hi - v) * ih / (hi - lo);
  let s = `<svg viewBox="0 0 ${W} ${H}" role="img" aria-label="${c.title}"><text class="t" x="${m.l}" y="16">${c.title}</text>`;
  for (let k = 0; k <= 4; k++) {
    const v = lo + (hi - lo) * k / 4, y = Y(v);
    s += `<line class="grid" x1="${m.l}" x2="${W - m.r}" y1="${y}" y2="${y}"/><text x="${m.l - 5}" y="${y + 3}" text-anchor="end">${Math.round(v * 10) / 10}</text>`;
  }
  c.labels.forEach((l, i) => s += `<text x="${X(i)}" y="${H - 8}" text-anchor="middle">${l}</text>`);
  (c.refs || []).forEach(r => { if (r.y >= lo && r.y <= hi) s += `<line x1="${m.l}" x2="${W - m.r}" y1="${Y(r.y)}" y2="${Y(r.y)}" stroke="${r.color}" stroke-dasharray="4 4" opacity=".55"/><text x="${W - m.r}" y="${Y(r.y) - 3}" text-anchor="end" style="fill:${r.color}">${r.label}</text>`; });
  c.series.forEach(se => {
    let d = '', pen = false;
    se.data.forEach((v, i) => { if (v === null) { pen = false; return; } d += (pen ? 'L' : 'M') + X(i) + ' ' + Y(v); pen = true; });
    s += `<path d="${d}" fill="none" stroke="${se.color}" stroke-width="2.5"/>`;
    se.data.forEach((v, i) => { if (v !== null) s += `<circle cx="${X(i)}" cy="${Y(v)}" r="4" fill="${se.color}"><title>${se.name}: ${v} (${c.labels[i]})</title></circle>`; });
  });
  if (c.series.length > 1) c.series.forEach((se, i) => s += `<text x="${W - m.r - 70 * (c.series.length - i - 1)}" y="16" text-anchor="end" style="fill:${se.color};font-weight:700">● ${se.name}</text>`);
  el.innerHTML = s + '</svg>';
}
