/**
 * GigaChef — клиентская работа с иконками.
 */

(function () {
  'use strict';

  const ICON_BASE = '/uploads/icon-pack';
  const LS_KEY_PREFIX = 'gc_svg_v1:';
  const LS_TTL_MS = 7 * 24 * 3600 * 1000;
  const MEM_CACHE = new Map();

  // ---------- localStorage ----------
  function lsGet(key) {
    try {
      const raw = localStorage.getItem(key);
      if (!raw) return null;
      const obj = JSON.parse(raw);
      if (!obj || !obj.t || Date.now() - obj.t > LS_TTL_MS) {
        localStorage.removeItem(key);
        return null;
      }
      return obj.v;
    } catch (e) { return null; }
  }
  function lsSet(key, value) {
    try {
      localStorage.setItem(key, JSON.stringify({ t: Date.now(), v: value }));
    } catch (e) {
      try {
        const keys = Object.keys(localStorage).filter(k => k.startsWith(LS_KEY_PREFIX));
        keys.slice(0, 50).forEach(k => localStorage.removeItem(k));
      } catch (_) {}
    }
  }

  // ---------- нормализация ----------
  function normalizeSvgClient(svg) {
    if (!svg) return '';
    svg = svg
      .replace(/<\?xml[^>]*\?>/gi, '')
      .replace(/<!DOCTYPE[^>]*>/gi, '')
      .replace(/<desc\b[^>]*>[\s\S]*?<\/desc>/gi, '')
      .replace(/<!--[\s\S]*?-->/g, '')
      .replace(/\b(fill|stroke)\s*=\s*"(?!#|currentColor|none|transparent|url\()([^"]*)"/gi,
               '$1="currentColor"');

    svg = svg.replace(/<svg\b([^>]*)>/i, (m, attrs) => {
      attrs = attrs.replace(/\s(width|height|style)\s*=\s*"[^"]*"/gi, '');
      return '<svg' + attrs + ' style="width:100%;height:100%;display:block;fill:currentColor;stroke:currentColor">';
    });

    return svg.replace(/\s{2,}/g, ' ').replace(/> </g, '><');
  }

  // ---------- загрузка SVG ----------
  async function fetchIcon(name, pack) {
    const key = pack + '/' + name;
    if (MEM_CACHE.has(key)) return MEM_CACHE.get(key);

    const lsVal = lsGet(LS_KEY_PREFIX + key);
    if (lsVal) {
      MEM_CACHE.set(key, lsVal);
      return lsVal;
    }

    try {
      const url = ICON_BASE + '/' + encodeURIComponent(pack) + '/' + encodeURIComponent(name) + '.svg';
      const res = await fetch(url, { credentials: 'same-origin', cache: 'force-cache' });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      let svg = await res.text();
      svg = normalizeSvgClient(svg);
      MEM_CACHE.set(key, svg);
      lsSet(LS_KEY_PREFIX + key, svg);
      return svg;
    } catch (e) {
      const fallback = '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="currentColor" opacity="0.3"/></svg>';
      MEM_CACHE.set(key, fallback);
      return fallback;
    }
  }

  // ---------- сборка HTML ----------
  function buildIconHtml(name, svg, opts) {
    const cls   = opts.class || '';
    const size  = opts.size  || 1.5;
    const color = opts.color || '';
    const safe  = String(name).replace(/"/g, '&quot;');
    const style = `display:inline-flex;width:${size}em;height:${size}em;`
                + `vertical-align:-0.15em;line-height:0;flex-shrink:0;`
                + (color ? `color:${color};` : '');
    return `<span class="gc-icon ${cls}" data-icon="${safe}" style="${style}">${svg}</span>`;
  }

  // ---------- публичный API ----------
  window.gcIcon = async function (name, opts) {
    opts = opts || {};
    const pack = opts.pack || document.documentElement.dataset.iconPack || 'default';
    const svg = await fetchIcon(name, pack);
    return buildIconHtml(name, svg, opts);
  };

  window.gcIconInto = async function (el, name, opts) {
    if (!el) return;
    el.innerHTML = await window.gcIcon(name, opts);
  };

  // ---------- переключение пака ----------
  window.gcSetIconPack = async function (pack) {
    if (!pack) return;
    document.documentElement.dataset.iconPack = pack;

    const nodes = Array.from(document.querySelectorAll('.gc-icon[data-icon]'));
    if (!nodes.length) return;

    const byName = new Map();
    nodes.forEach(el => {
      const n = el.dataset.icon;
      if (!byName.has(n)) byName.set(n, []);
      byName.get(n).push(el);
    });

    await Promise.all(Array.from(byName.entries()).map(async ([name, els]) => {
      const svg = await fetchIcon(name, pack);
      els.forEach(el => { el.innerHTML = svg; });
    }));
  };

  // ---------- применение цветов ----------
  window.gcApplyIconColors = function (colors) {
    if (!colors) return;
    const root = document.documentElement;
    const map = {
      primary: '--icon-color',
      muted:   '--icon-color-muted',
      accent:  '--icon-color-accent',
      success: '--icon-color-success',
      danger:  '--icon-color-danger',
      warning: '--icon-color-warning',
      info:    '--icon-color-info',
    };
    for (const [key, cssVar] of Object.entries(map)) {
      const val = colors[key];
      if (val && /^(#[0-9a-f]{3,8}|rgba?\(|hsla?\(|var\()/i.test(val)) {
        root.style.setProperty(cssVar, val);
      } else {
        root.style.removeProperty(cssVar);
      }
    }
  };

  // ---------- автоподхват сохранённого пака ----------
  document.addEventListener('DOMContentLoaded', () => {
    try {
      const saved = localStorage.getItem('gigachef_icon_pack');
      const current = document.documentElement.dataset.iconPack;
      if (saved && saved !== current && window.gcSetIconPack) {
        window.gcSetIconPack(saved);
      }
    } catch (e) {}
  });

})();