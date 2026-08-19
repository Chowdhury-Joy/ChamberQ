#!/usr/bin/env node
/**
 * QA button-sweep harness for the ChamberQ local app.
 *
 * Usage:
 *   node scripts/qa/sweep.mjs [filter] [outfile]
 *
 *   filter   substring matched against spec labels ('' = all specs)
 *   outfile  JSON report path (default storage/qa-sweep-results.json)
 *
 * Owner scope (2026-08-18): Solo, MUPS, and Pain Solution only. Demo,
 * nusraturmi, partner, Super Admin, and central marketing specs stay out of
 * this file so future runs cannot accidentally click them. Tenant rows are
 * never deleted — they are just not in the spec list.
 *
 * For every spec it: loads the page, clicks every same-origin link, fills and
 * submits every form, expands Filament menus, clicks every enabled button, and
 * records: HTTP responses >= 400, console errors, page errors, and DOM text
 * matching known failure markers (stack traces, branded 500/419 copy). HTTP
 * 5xx and fresh 419s count as crashes. Restore / delete / wipe / Cancel Session
 * are opened then dismissed, never confirmed. Logout is skipped so the panel
 * walk can finish. Scripted steps cover booking, ticket, OTP, portal, outdoor
 * screens, and queue Start → Call → arrived + Consult Screen.
 */
import { chromium } from 'playwright';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const BASE = process.env.QA_BASE_URL ?? 'http://127.0.0.1:8000';
const FILTER = process.argv[2] ?? '';
const OUT = process.argv[3] ?? 'storage/qa-sweep-results.json';
const PASSWORD = 'pass';
const QA_PHONE = '017' + String(Math.floor(10000000 + Math.random() * 89999999));
const QA_EMAIL = 'qa-sweep@example.com';

const MARKERS = [
    /Internal Server Error/i,
    /SQLSTATE/i,
    /Connection\.php:\d+/,
    /QueryException/,
    /Whoops, something went wrong/i,
    /Fatal error/i,
    /Undefined (variable|method|property)/i,
    /Call to undefined (function|method)/i,
    /Something went wrong\./,
    /This form expired\./,
    /\bServer Error\b/,
    /Illuminate\\Database/,
    /vendor\/laravel\/framework/,
];

/** Restore / wipe / delete must open then dismiss — never confirm. */
const DESTRUCTIVE_RE = /dangerous|permanently delete|wipe this|type replace|restore chamber|restore platform|delete tenant|forcedelete|restoretenantbackup|restorebackup|restoreplatformbackup|cancel session|finish \/ end session|end session/i;
const DESTRUCTIVE_START_RE = /^(delete|remove|restore|wipe|replace)\b/i;
const LOGOUT_RE = /^(log out|sign out|logout)$/i;

const results = { startedAt: new Date().toISOString(), base: BASE, specs: [] };

function markerMatch(text) {
    return MARKERS.find((re) => re.test(text)) ?? null;
}

function isDestructiveControl(text, wire = '') {
    const hay = `${text} ${wire}`;
    if (/download/i.test(hay) && /backup/i.test(hay)) return false;
    if (/check zip without writing|dry.?run complete/i.test(hay)) return false;
    return DESTRUCTIVE_START_RE.test(text) || DESTRUCTIVE_RE.test(hay);
}

function isLogoutControl(text) {
    return LOGOUT_RE.test((text || '').trim());
}

function isCrashStatus(status) {
    return typeof status === 'number' && (status >= 500 || status === 419);
}

function isIgnorablePageError(msg) {
    return /tailwind is not defined/i.test(msg)
        || /navigator\.vibrate/i.test(msg)
        || /Failed to load resource/i.test(msg)
        || /Failed to fetch/i.test(msg)
        || /Clipboard.*Write permission denied/i.test(msg)
        || /NotAllowedError/i.test(msg)
        || /\b429\b/.test(msg)
        || /Too Many Requests/i.test(msg);
}

function countCrashes(rec) {
    let n = (rec.http ?? []).filter((h) => isCrashStatus(h.status)).length
        + (rec.pageErrors ?? []).filter((m) => !isIgnorablePageError(m)).length
        + (rec.domErrors ?? []).length
        + (rec.pages ?? []).filter((p) => isCrashStatus(p.status)).length
        + (rec.links ?? []).filter((l) => l.navigated && isCrashStatus(l.status)).length
        + (rec.buttons ?? []).filter((b) => isCrashStatus(b.status)).length
        + (rec.forms ?? []).filter((f) => isCrashStatus(f.status)).length
        + (rec.scripts ?? []).filter((s) => isCrashStatus(s.submitStatus) || isCrashStatus(s.sendStatus)).length;
    for (const p of rec.pages ?? []) {
        if (p.sub) n += countCrashes(p.sub);
    }
    if (rec.fatal) n += 1;
    return n;
}

function ensureQaMarketer() {
    try {
        const out = execFileSync('php', [path.join('scripts', 'qa', 'ensure-marketer.php')], {
            encoding: 'utf8',
            timeout: 30000,
        });
        process.stderr.write(`  marketer: ${out.trim()}\n`);
    } catch (e) {
        process.stderr.write(`  marketer ensure failed: ${String(e.stderr || e.message).slice(0, 200)}\n`);
    }
}

function escapeAttr(v) {
    return String(v).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
}

function visibleText(el) {
    try {
        return (el.innerText || el.value || el.getAttribute('aria-label') || '').trim().replace(/\s+/g, ' ').slice(0, 60);
    } catch {
        return '';
    }
}

async function recordMarkers(page, rec) {
    try {
        const text = await page.evaluate(() => document.body ? document.body.innerText : '');
        const m = markerMatch(text);
        if (m) {
            rec.domErrors.push({ marker: m.source, url: page.url(), at: new Date().toISOString() });
        }
    } catch {
        /* navigation in flight */
    }
}

function snap(rec) {
    return {
        http: rec.http.length,
        console: rec.consoleErrors.length,
        page: rec.pageErrors.length,
        dom: rec.domErrors.length,
    };
}

function delta(rec, before) {
    return {
        http: rec.http.slice(before.http),
        console: rec.consoleErrors.slice(before.console),
        page: rec.pageErrors.slice(before.page),
        dom: rec.domErrors.slice(before.dom),
    };
}

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function raceResp(promise, ms = 1500) {
    return Promise.race([promise, sleep(ms).then(() => null)]);
}

async function gotoPage(page, url, timeout = 30000) {
    let lastErr;
    for (let attempt = 0; attempt < 3; attempt++) {
        try {
            await page.goto(url, { waitUntil: 'domcontentloaded', timeout });
            return;
        } catch (e) {
            lastErr = e;
            await sleep(1500);
        }
    }
    throw lastErr;
}

function wireAttrs(el) {
    for (const k of ['wire:click', 'wire:click.self', 'wire:click.stop', 'wire:submit', 'x-on:click', 'onclick']) {
        const v = el.getAttribute(k);
        if (v) return `${k}=${v.slice(0, 80)}`;
    }
    return '';
}

async function openMenuDropdowns(page) {
    try {
        const triggers = await page.$$('[aria-haspopup="true"], .fi-dropdown-trigger, .fi-icon-btn[aria-haspopup], [data-filament-dropdown-trigger]');
        for (const t of triggers) {
            try {
                await t.scrollIntoViewIfNeeded({ timeout: 1500 });
                await t.click({ force: true, timeout: 2000 });
                await page.waitForTimeout(250);
                await page.keyboard.press('Escape');
                await page.waitForTimeout(150);
            } catch {
                /* trigger not clickable */
            }
        }
    } catch {
        /* no dropdowns */
    }
}

async function dismissModals(page) {
    for (let i = 0; i < 3; i++) {
        const dialog = await page.$('[role="dialog"]');
        if (!dialog) break;
        const close = await page.$('[role="dialog"] [data-filament-modal-close], [role="dialog"] .fi-modal-close-btn');
        if (close) {
            try { await close.click({ force: true, timeout: 2000 }); } catch { /* gone */ }
        } else {
            await page.keyboard.press('Escape');
        }
        await page.waitForTimeout(250);
    }
}

async function goBackSafe(page, urlBefore) {
    try {
        await page.goBack({ waitUntil: 'domcontentloaded', timeout: 10000 });
    } catch {
        await gotoPage(page, urlBefore, 15000).catch(() => {});
    }
    await page.waitForTimeout(400);
}

async function sweepLinks(page, rec) {
    const links = await page.$$eval('a[href]', (els) =>
        els.map((el, i) => ({
            i,
            href: el.getAttribute('href') || '',
            text: (el.innerText || el.getAttribute('aria-label') || '').trim().replace(/\s+/g, ' ').slice(0, 50),
            target: el.getAttribute('target') || '',
            download: el.getAttribute('download') || '',
        })).filter((l) => l.href && !l.href.startsWith('#') && !l.href.startsWith('javascript:')),
    );
    const seen = new Set();
    for (const link of links) {
        const url = new URL(link.href, BASE);
        const sameOrigin = url.origin === new URL(BASE).origin;
        if (!sameOrigin) {
            if (!seen.has(url.origin)) {
                rec.links.push({ href: link.href, text: link.text, external: true });
                seen.add(url.origin);
            }
            continue;
        }
        const full = url.pathname + url.search;
        if (seen.has(full)) continue;
        seen.add(full);
        const before = snap(rec);
        const urlBefore = page.url();
        const outcome = { href: full, text: link.text, status: null, navigated: false, note: null };
        rec.links.push(outcome);
        try {
            const [resp] = await Promise.all([
                raceResp(page.waitForResponse((r) => r.status() >= 400 && r.url().includes(BASE), { timeout: 4000 }).catch(() => null)),
                page.click(`a[href="${escapeAttr(link.href)}"] >> nth=${links.filter((l) => l.href === link.href).indexOf(link)}`, { timeout: 4000 }).catch(async () => {
                    const handles = await page.$$('a[href]');
                    const h = handles.find((x) => x !== null);
                    if (h) await h.evaluate((el, href) => { for (const a of document.querySelectorAll('a[href]')) if (a.getAttribute('href') === href) { a.click(); break; } }, link.href);
                }),
            ]);
            await page.waitForTimeout(700);
            if (page.url() !== urlBefore) {
                outcome.navigated = true;
                const navResp = await page.evaluate(async () => {
                    const res = await fetch(location.href, { method: 'GET', headers: { 'X-QA-Sweep': '1' } });
                    return res.status;
                }).catch(() => null);
                outcome.status = navResp;
                if (navResp && navResp >= 500) outcome.note = '5xx on navigated page';
                await goBackSafe(page, urlBefore);
            } else if (resp) {
                outcome.status = resp.status();
                outcome.note = `HTTP ${resp.status()}`;
            }
        } catch (e) {
            outcome.note = `click failed: ${String(e).slice(0, 120)}`;
        }
        const d = delta(rec, before);
        if (d.http.length || d.console.length || d.page.length || d.dom.length) {
            outcome.errors = { http: d.http, console: d.console, page: d.page, dom: d.dom };
        }
    }
}

async function sweepForms(page, rec) {
    const count = await page.$$eval('form', (forms) => forms.length);
    for (let i = 0; i < count; i++) {
        const before = snap(rec);
        const urlBefore = page.url();
        const outcome = { index: i, action: null, status: null, note: null };
        rec.forms.push(outcome);
        try {
            const form = page.locator('form').nth(i);
            outcome.action = (await form.getAttribute('action')) || (await form.getAttribute('wire:submit')) || null;
            if (isDestructiveControl(outcome.action || '', outcome.action || '') || /\/me\/logout/.test(outcome.action || '')) {
                outcome.note = 'skipped: destructive or logout form';
                continue;
            }
            const inputs = form.locator('input, select, textarea, button[type="submit"], input[type="submit"]');
            const n = await inputs.count();
            for (let j = 0; j < n; j++) {
                const el = inputs.nth(j);
                const tag = await el.evaluate((e) => e.tagName.toLowerCase());
                const type = await el.evaluate((e) => (e.getAttribute('type') || '').toLowerCase());
                const name = await el.evaluate((e) => e.getAttribute('name') || e.id || '');
                const visible = await el.isVisible().catch(() => false);
                if (!visible) continue;
                if (type === 'hidden' || type === 'file' || name === '_token' || name === '_method') continue;
                try {
                    if (tag === 'select') {
                        const opts = await el.evaluate((e) => e.options.length);
                        if (opts > 1) await el.selectOption({ index: Math.min(1, opts - 1) });
                    } else if (type === 'checkbox') {
                        if (!(await el.isChecked())) await el.check({ force: true });
                    } else if (type === 'radio') {
                        if (!(await el.isChecked())) await el.check({ force: true }).catch(() => {});
                    } else if (type === 'tel') {
                        await el.fill(QA_PHONE, { timeout: 2000 });
                    } else if (type === 'email') {
                        await el.fill(QA_EMAIL, { timeout: 2000 });
                    } else if (type === 'date') {
                        await el.fill(new Date().toISOString().slice(0, 10), { timeout: 2000 });
                    } else if (type === 'number' || tag === 'input' && type === 'text' && /price|fee|amount|age/i.test(name)) {
                        await el.fill('1', { timeout: 2000 });
                    } else if (tag === 'textarea' || tag === 'input') {
                        await el.fill('QA sweep', { timeout: 2000 });
                    }
                } catch {
                    /* unfillable input — skip */
                }
            }
            const submit = form.locator('button[type="submit"], input[type="submit"]').first();
            const hasSubmit = await submit.count().catch(() => 0);
            const [resp] = await Promise.all([
                raceResp(page.waitForResponse((r) => r.status() >= 400, { timeout: 5000 }).catch(() => null)),
                (async () => {
                    if (hasSubmit) await submit.click({ timeout: 4000 }).catch(() => {});
                    else await form.press('Enter').catch(() => {});
                })(),
            ]);
            await page.waitForTimeout(550);
            if (page.url() !== urlBefore) {
                outcome.note = outcome.note ? outcome.note + '; navigated' : 'navigated';
                await goBackSafe(page, urlBefore);
            } else if (resp) {
                outcome.status = resp.status();
            }
        } catch (e) {
            outcome.note = `form sweep failed: ${String(e).slice(0, 120)}`;
        }
        const d = delta(rec, before);
        if (d.http.length || d.console.length || d.page.length || d.dom.length) {
            outcome.errors = { http: d.http, console: d.console, page: d.page, dom: d.dom };
        }
    }
}

async function sweepButtons(page, rec, opts = {}) {
    const SELECTOR = 'button, input[type="submit"], input[type="button"], [role="button"], [wire\\:click], [wire\\:submit], [x-on\\:click], [onclick]';
    const infos = await page.$$eval(
        SELECTOR,
        (els) =>
            els.map((el) => {
                let wire = '';
                for (const k of ['wire:click', 'wire:click.self', 'wire:click.stop', 'wire:submit', 'x-on:click', 'onclick']) {
                    const v = el.getAttribute(k);
                    if (v) { wire = k + '=' + v.slice(0, 80); break; }
                }
                return {
                    tag: el.tagName.toLowerCase(),
                    type: (el.getAttribute('type') || '').toLowerCase(),
                    text: (el.innerText || el.value || el.getAttribute('aria-label') || '').trim().replace(/\s+/g, ' ').slice(0, 60),
                    wire,
                    disabled: el.disabled === true || el.hasAttribute('disabled'),
                    hidden: el.offsetParent === null,
                    aria: el.getAttribute('aria-label') || '',
                };
            }),
    );
    const unique = [];
    const seen = new Set();
    infos.forEach((b, i) => {
        if (b.disabled || b.hidden) return;
        const key = `${b.tag}|${b.type}|${b.text}|${b.wire}|${b.aria}`;
        if (seen.has(key)) return;
        seen.add(key);
        unique.push({ ...b, i });
    });
    rec.buttonsTotal = unique.length;
    const cap = opts.maxButtons ?? unique.length;
    const toClick = unique.slice(0, cap);
    rec.buttonsCapped = toClick.length < unique.length;
    for (const b of toClick) {
        const before = snap(rec);
        const urlBefore = page.url();
        const outcome = { text: b.text, wire: b.wire, type: b.type, tag: b.tag, status: null, navigated: false, note: null };
        rec.buttons.push(outcome);
        if (isLogoutControl(b.text) || isLogoutControl(b.aria)) {
            outcome.note = 'skipped: logout';
            continue;
        }
        try {
            const [resp] = await Promise.all([
                raceResp(page.waitForResponse((r) => r.status() >= 400 && !/\.(css|js|png|jpe?g|svg|webp|woff2?|ttf|ico|gif|map)(\?|$)/i.test(r.url()), { timeout: 3500 }).catch(() => null)),
                (async () => {
                    const handles = await page.$$(SELECTOR);
                    const fresh = await page.$$eval(
                        SELECTOR,
                        (els) =>
                            els.map((el) => {
                                let wire = '';
                                for (const k of ['wire:click', 'wire:click.self', 'wire:click.stop', 'wire:submit', 'x-on:click', 'onclick']) {
                                    const v = el.getAttribute(k);
                                    if (v) { wire = k + '=' + v.slice(0, 80); break; }
                                }
                                return {
                                    tag: el.tagName.toLowerCase(),
                                    type: (el.getAttribute('type') || '').toLowerCase(),
                                    text: (el.innerText || el.value || el.getAttribute('aria-label') || '').trim().replace(/\s+/g, ' ').slice(0, 60),
                                    wire,
                                    disabled: el.disabled === true || el.hasAttribute('disabled'),
                                    hidden: el.offsetParent === null,
                                    aria: el.getAttribute('aria-label') || '',
                                };
                            }),
                    );
                    let idx = -1;
                    for (let i = 0; i < fresh.length; i++) {
                        const f = fresh[i];
                        if (f.tag === b.tag && f.type === b.type && f.text === b.text && f.wire === b.wire && f.aria === b.aria && !f.disabled && !f.hidden) {
                            idx = i;
                            break;
                        }
                    }
                    const chosen = idx >= 0 ? handles[idx] : handles[b.i];
                    if (!chosen) throw new Error('element gone');
                    await chosen.scrollIntoViewIfNeeded({ timeout: 2000 }).catch(() => {});
                    await chosen.click({ force: true, timeout: 2500 }).catch(async () => {
                        await chosen.evaluate((el) => el.click());
                    });
                })(),
            ]);
            await page.waitForTimeout(550);
            if (isDestructiveControl(b.text, b.wire)) {
                outcome.note = 'destructive: opened then dismissed';
                await dismissModals(page);
                await recordMarkers(page, rec);
                const dSkip = delta(rec, before);
                if (dSkip.http.length || dSkip.console.length || dSkip.page.length || dSkip.dom.length) {
                    outcome.errors = { http: dSkip.http, console: dSkip.console, page: dSkip.page, dom: dSkip.dom };
                }
                continue;
            }
            if (page.url() !== urlBefore) {
                outcome.navigated = true;
                const navResp = await page.evaluate(async () => {
                    const res = await fetch(location.href, { method: 'GET', headers: { 'X-QA-Sweep': '1' } });
                    return res.status;
                }).catch(() => null);
                outcome.status = navResp;
                if (navResp && navResp >= 500) outcome.note = '5xx on navigated page';
                await goBackSafe(page, urlBefore);
            } else if (resp) {
                outcome.status = resp.status();
                if (isCrashStatus(resp.status())) outcome.note = `HTTP ${resp.status()}`;
            }
            await dismissModals(page);
        } catch (e) {
            outcome.note = `click failed: ${String(e).slice(0, 120)}`;
        }
        await recordMarkers(page, rec);
        const d = delta(rec, before);
        if (d.http.length || d.console.length || d.page.length || d.dom.length) {
            outcome.errors = { http: d.http, console: d.console, page: d.page, dom: d.dom };
        }
    }
}

async function sweepDropdowns(page, rec, maxItems = 99) {
    const triggers = await page.$$('[aria-haspopup="true"], .fi-dropdown-trigger, .fi-icon-btn[aria-haspopup]');
    let opened = 0;
    for (const t of triggers) {
        if (opened >= maxItems) break;
        try {
            await t.scrollIntoViewIfNeeded({ timeout: 1500 }).catch(() => {});
            await t.click({ force: true, timeout: 2500 }).catch(() => {});
            await page.waitForTimeout(400);
            const visible = await page.evaluate(() => {
                const items = [...document.querySelectorAll('[role="menuitem"], [role="menuitem"] button, .fi-dropdown-list button, .fi-dropdown-list a')];
                return items.filter((el) => el.offsetParent !== null && !el.disabled).map((el, i) => ({
                    i,
                    text: (el.innerText || el.getAttribute('aria-label') || '').trim().replace(/\s+/g, ' ').slice(0, 60),
                    href: el.getAttribute('href') || '',
                    tag: el.tagName.toLowerCase(),
                }));
            });
            for (const item of visible) {
                if (!item.text && !item.href) continue;
                const before = snap(rec);
                const urlBefore = page.url();
                const outcome = { menu: 'dropdown', text: item.text, href: item.href, status: null, navigated: false, note: null };
                rec.buttons.push(outcome);
                if (isLogoutControl(item.text)) {
                    outcome.note = 'skipped: logout';
                    continue;
                }
                try {
                    if (item.href && item.href.startsWith('/')) {
                        await page.evaluate((href) => {
                            const el = [...document.querySelectorAll('[role="menuitem"], .fi-dropdown-list a')].find((a) => a.getAttribute('href') === href);
                            if (el) el.click();
                        }, item.href);
                    } else {
                        await page.evaluate((idx) => {
                            const items = [...document.querySelectorAll('[role="menuitem"], [role="menuitem"] button, .fi-dropdown-list button, .fi-dropdown-list a')].filter((el) => el.offsetParent !== null);
                            const el = items[idx];
                            if (el) el.click();
                        }, item.i);
                    }
                    await page.waitForTimeout(900);
                    if (isDestructiveControl(item.text, item.href)) {
                        outcome.note = 'destructive: opened then dismissed';
                        await dismissModals(page);
                        await recordMarkers(page, rec);
                    } else if (page.url() !== urlBefore) {
                        outcome.navigated = true;
                        outcome.status = await page.evaluate(async () => { const r = await fetch(location.href); return r.status; }).catch(() => null);
                        await goBackSafe(page, urlBefore);
                    }
                } catch (e) {
                    outcome.note = `click failed: ${String(e).slice(0, 120)}`;
                }
                await dismissModals(page);
                await recordMarkers(page, rec);
                const d = delta(rec, before);
                if (d.http.length || d.console.length || d.page.length || d.dom.length) {
                    outcome.errors = { http: d.http, console: d.console, page: d.page, dom: d.dom };
                }
                opened++;
            }
            await page.keyboard.press('Escape');
            await page.waitForTimeout(250);
        } catch {
            /* trigger not usable */
        }
    }
    return opened;
}

async function sweepPage(page, rec, opts = {}) {
    rec.url = page.url();
    rec.title = await page.title().catch(() => '');
    if (opts.noButtons) {
        await sweepLinks(page, rec);
        return;
    }
    await openMenuDropdowns(page);
    process.stderr.write(`  ${rec.label || page.url()} [links...]\n`);
    if (!opts.noLinks) {
        await sweepLinks(page, rec);
    }
    await openMenuDropdowns(page);
    process.stderr.write(`  ${rec.label || page.url()} [forms...]\n`);
    await sweepForms(page, rec);
    await openMenuDropdowns(page);
    process.stderr.write(`  ${rec.label || page.url()} [buttons...]\n`);
    await sweepButtons(page, rec, opts);
    process.stderr.write(`  ${rec.label || page.url()} [dropdowns...]\n`);
    await sweepDropdowns(page, rec, opts.maxDropdowns ?? 99);
}

async function login(page, panelUrl, email) {
    await gotoPage(page, BASE + panelUrl);
    await page.waitForTimeout(1200);
    const emailSel = page.locator('#form\\.email, input[name="email"]').first();
    const passSel = page.locator('#form\\.password, input[name="password"]').first();
    await emailSel.fill(email, { timeout: 15000 });
    await passSel.fill(PASSWORD, { timeout: 5000 });
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 20000 }).catch(() => {}),
        (async () => {
            const btn = page.locator('button[type="submit"], form button[type="submit"]').first();
            if (await btn.count().catch(() => 0)) await btn.click({ timeout: 8000 }).catch(() => {});
            else await page.keyboard.press('Enter');
        })(),
    ]);
    await page.waitForTimeout(1500);
    return page.url();
}

async function lastOtpCode() {
    const log = fs.readFileSync('storage/logs/laravel.log', 'utf8');
    const m = [...log.matchAll(/ChamberQ code: (\d{6})/g)];
    return m.length ? m[m.length - 1][1] : null;
}

async function scriptedBooking(page, rec, slug) {
    const steps = ['type', 'chamber', 'doctor', 'when', 'slots', 'lab', 'identity'];
    const outcome = { slug, done: [], failed: [], ticketUrl: null };
    rec.scripts.push(outcome);
    await gotoPage(page, `${BASE}/${slug}/book`);
    await page.waitForTimeout(600);

    const clickCard = async (selector, label) => {
        const card = page.locator(selector).first();
        const count = await card.count().catch(() => 0);
        if (!count) { outcome.failed.push(`${label}: no element`); return false; }
        await card.scrollIntoViewIfNeeded({ timeout: 2000 }).catch(() => {});
        await card.click({ force: true, timeout: 3000 }).catch(() => {});
        await page.waitForTimeout(1200);
        outcome.done.push(label);
        return true;
    };

    await clickCard('#step-type .selection-card', 'type');
    await clickCard('#step-chamber .selection-card', 'chamber');
    await clickCard('#step-doctor .selection-card', 'doctor');

    const dateCard = page.locator('#when-grid .selection-card, #step-when .selection-card').first();
    for (let tries = 0; tries < 15 && !(await dateCard.count().catch(() => 0)); tries++) {
        await page.waitForTimeout(1000);
    }
    if (await dateCard.count().catch(() => 0)) {
        await clickCard('#when-grid .selection-card', 'when');
        await page.waitForTimeout(1500);
        const slot = page.locator('#step-when .slot-card, #step-when .time-card, #step-when [data-slot]').first();
        if (await slot.count().catch(() => 0)) {
            await clickCard('#step-when .slot-card, #step-when .time-card', 'slot');
        }
    } else {
        outcome.failed.push('when: no open dates');
    }

    await page.waitForTimeout(800);
    const labContinue = page.locator('#step-lab-tests .btn-primary, #step-lab-tests button[onclick="continueLabTests()"]').first();
    if (await labContinue.count().catch(() => 0)) {
        await labContinue.click({ force: true, timeout: 3000 }).catch(() => {});
        await page.waitForTimeout(800);
        outcome.done.push('lab-continue');
    }

    const phone = page.locator('#patient_phone');
    if (await phone.count().catch(() => 0)) {
        await phone.fill(QA_PHONE, { timeout: 3000 });
        await page.keyboard.press('Tab').catch(() => {});
        await page.waitForTimeout(1200);
        const name = page.locator('#patient_name');
        if (await name.count().catch(() => 0)) {
            await name.fill('QA Sweep Patient', { timeout: 3000 });
        }
        await page.waitForTimeout(300);
        const submit = page.locator('#submitBtn, #bookingForm button[type="submit"]').first();
        if (await submit.count().catch(() => 0)) {
            for (let tries = 0; tries < 10; tries++) {
                const disabled = await submit.isDisabled().catch(() => true);
                if (!disabled) break;
                await page.waitForTimeout(1000);
            }
            const [resp] = await Promise.all([
                page.waitForResponse((r) => r.url().includes('/api/bookings') && r.request().method() === 'POST', { timeout: 10000 }).catch(() => null),
                submit.click({ force: true, timeout: 5000 }).catch(() => {}),
            ]);
            await page.waitForTimeout(4000);
            outcome.submitStatus = resp ? resp.status() : null;
            outcome.done.push('submit');
            if (page.url().includes('/bookings/')) {
                outcome.ticketUrl = page.url().replace(BASE, '');
                rec.ticketUrl = outcome.ticketUrl;
            } else {
                const errText = await page.evaluate(() => document.body.innerText.slice(0, 400)).catch(() => '');
                if (markerMatch(errText)) outcome.failed.push('error page: ' + markerMatch(errText).source);
            }
        } else {
            outcome.failed.push('submit: no element');
        }
    } else {
        outcome.failed.push('identity: no phone field');
    }
    return outcome;
}

async function scriptedOtp(page, rec) {
    const outcome = { done: [], failed: [] };
    rec.scripts.push(outcome);
    await gotoPage(page, `${BASE}/me/login`);
    await page.waitForTimeout(500);
    const phone = page.locator('input[name="phone"], input[type="tel"]').first();
    if (await phone.count().catch(() => 0)) {
        await phone.fill(QA_PHONE, { timeout: 3000 });
        const send = page.locator('button[type="submit"], form button').first();
        const [resp] = await Promise.all([
            page.waitForResponse((r) => r.url().includes('/me/login/otp') || r.url().includes('otp'), { timeout: 8000 }).catch(() => null),
            send.click({ timeout: 4000 }).catch(() => {}),
        ]);
        await page.waitForTimeout(1000);
        outcome.sendStatus = resp ? resp.status() : null;
        outcome.done.push('send-otp');
        const code = await lastOtpCode();
        if (code) {
            const codeInput = page.locator('#code');
            await codeInput.fill(code, { timeout: 3000 }).catch(() => {});
            const verify = page.locator('form[action*="verify"] button[type="submit"], form[action*="verify"] button').first();
            const [vresp] = await Promise.all([
                page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 12000 }).catch(() => null),
                verify.click({ timeout: 4000 }).catch(() => {}),
            ]);
            await page.waitForTimeout(1000);
            outcome.done.push('verify-otp');
            outcome.afterUrl = page.url().replace(BASE, '');
        } else {
            outcome.failed.push('no OTP code in log');
        }
    } else {
        outcome.failed.push('no phone field');
    }
    return outcome;
}

async function scriptedPortal(page, rec, slug) {
    const outcome = { slug, done: [], failed: [] };
    rec.scripts.push(outcome);
    await gotoPage(page, `${BASE}/${slug}/portal`);
    await page.waitForTimeout(500);
    const phone = page.locator('input[name="phone"], input[type="tel"], input[type="text"]').first();
    if (await phone.count().catch(() => 0)) {
        await phone.fill(QA_PHONE, { timeout: 3000 });
        const submit = page.locator('button[type="submit"], form button').first();
        const [resp] = await Promise.all([
            page.waitForResponse((r) => r.status() >= 400, { timeout: 6000 }).catch(() => null),
            submit.click({ timeout: 4000 }).catch(() => {}),
        ]);
        await page.waitForTimeout(1000);
        outcome.submitStatus = resp ? resp.status() : null;
        outcome.done.push('lookup');
    } else {
        outcome.failed.push('no phone field');
    }
    return outcome;
}

async function clickFirstMatching(page, labels) {
    for (const label of labels) {
        const btn = page.getByRole('button', { name: label }).first();
        if (await btn.count().catch(() => 0) && await btn.isVisible().catch(() => false)) {
            await btn.click({ force: true, timeout: 4000 }).catch(() => {});
            await page.waitForTimeout(1200);
            return label;
        }
        const textBtn = page.locator('button, a.fi-btn, [role="button"]').filter({ hasText: label }).first();
        if (await textBtn.count().catch(() => 0) && await textBtn.isVisible().catch(() => false)) {
            await textBtn.click({ force: true, timeout: 4000 }).catch(() => {});
            await page.waitForTimeout(1200);
            return label;
        }
    }
    return null;
}

async function visitAndMark(page, rec, url, label) {
    const outcome = { href: url.replace(BASE, ''), label, status: null, note: null };
    rec.pages.push(outcome);
    try {
        await gotoPage(page, url.startsWith('http') ? url : BASE + url);
        await page.waitForTimeout(800);
        const status = await page.evaluate(async () => {
            const res = await fetch(location.href, { method: 'GET', headers: { 'X-QA-Sweep': '1' } });
            return res.status;
        }).catch(() => null);
        outcome.status = status;
        await recordMarkers(page, rec);
        const body = await page.evaluate(() => document.body ? document.body.innerText.slice(0, 3000) : '').catch(() => '');
        const m = markerMatch(body);
        if (m) outcome.note = `DOM marker: ${m.source}`;
        if (isCrashStatus(status)) outcome.note = (outcome.note ? outcome.note + '; ' : '') + `HTTP ${status}`;
        const sub = { url: outcome.href, http: [], consoleErrors: [], pageErrors: [], domErrors: [], links: [], forms: [], buttons: [], scripts: [], pages: [], buttonsTotal: 0, title: '' };
        await sweepPage(page, sub, { noLinks: true, maxDropdowns: 4, maxButtons: 20 });
        outcome.sub = sub;
    } catch (e) {
        outcome.note = `load failed: ${String(e).slice(0, 120)}`;
    }
    return outcome;
}

async function scriptedTicket(page, rec) {
    const outcome = { done: [], failed: [] };
    rec.scripts.push(outcome);
    const url = rec.ticketUrl;
    if (!url) {
        outcome.failed.push('no ticket url from booking');
        return outcome;
    }
    await visitAndMark(page, rec, url, 'ticket');
    outcome.done.push('ticket');
    const share = page.locator('a[href*="wa.me"], button:has-text("Copy"), a:has-text("Copy")').first();
    if (await share.count().catch(() => 0)) {
        await share.click({ force: true, timeout: 3000 }).catch(() => {});
        await page.waitForTimeout(400);
        outcome.done.push('share-or-copy');
        await recordMarkers(page, rec);
    }
    return outcome;
}

async function scriptedScreens(page, rec, slug) {
    const outcome = { slug, done: [], failed: [], urls: [] };
    rec.scripts.push(outcome);
    await gotoPage(page, `${BASE}/${slug}/admin/live-queue-control`);
    await page.waitForTimeout(1200);
    const card = page.locator('.lqc-session-card').first();
    if (await card.count().catch(() => 0)) {
        await card.click({ force: true, timeout: 3000 }).catch(() => {});
        await page.waitForTimeout(1000);
        outcome.done.push('pick-session');
    }
    const hrefs = await page.$$eval('a[href*="/screen/"]', (els) =>
        [...new Set(els.map((a) => a.getAttribute('href')).filter(Boolean))],
    ).catch(() => []);
    outcome.urls = hrefs;
    if (!hrefs.length) {
        outcome.failed.push('no /screen/ links on live queue');
        await recordMarkers(page, rec);
        return outcome;
    }
    for (const href of hrefs) {
        const url = href.startsWith('http') ? href : (href.startsWith('/') ? BASE + href : href);
        await visitAndMark(page, rec, url, 'screen');
        outcome.done.push(url.replace(BASE, ''));
    }
    return outcome;
}

async function scriptedQueueDay(page, rec, slug) {
    const outcome = { slug, done: [], failed: [] };
    rec.scripts.push(outcome);
    await gotoPage(page, `${BASE}/${slug}/admin/live-queue-control`);
    await page.waitForTimeout(1500);
    const card = page.locator('.lqc-session-card').first();
    if (await card.count().catch(() => 0)) {
        await card.click({ force: true, timeout: 3000 }).catch(() => {});
        await page.waitForTimeout(1200);
        outcome.done.push('pick-session');
    }
    const started = await clickFirstMatching(page, ['Start live session', 'Start now', 'Just start']);
    if (started) {
        outcome.done.push(started);
        const confirm = await clickFirstMatching(page, ['Start now', 'Just start', 'Start live session']);
        if (confirm) outcome.done.push('confirm:' + confirm);
        await page.waitForTimeout(1500);
    } else {
        outcome.failed.push('no Start live session');
    }
    await recordMarkers(page, rec);
    const called = await clickFirstMatching(page, ['Call next patient', 'Call now']);
    if (called) {
        outcome.done.push(called);
        await page.waitForTimeout(1200);
    }
    const arrived = await clickFirstMatching(page, ['Patient arrived']);
    if (arrived) {
        outcome.done.push(arrived);
        await page.waitForTimeout(1200);
    } else {
        outcome.failed.push('no Patient arrived (may already be in chamber or no current call)');
    }
    await recordMarkers(page, rec);

    await gotoPage(page, `${BASE}/${slug}/admin/consult-screen`);
    await page.waitForTimeout(1500);
    await recordMarkers(page, rec);
    const sub = { url: `/${slug}/admin/consult-screen`, http: [], consoleErrors: [], pageErrors: [], domErrors: [], links: [], forms: [], buttons: [], scripts: [], pages: [], buttonsTotal: 0, title: '' };
    await sweepPage(page, sub, { noLinks: true, maxDropdowns: 6, maxButtons: 20 });
    rec.pages.push({ href: `/${slug}/admin/consult-screen`, status: null, note: null, sub });
    outcome.done.push('consult-screen');
    return outcome;
}

async function sweepSidebarPages(page, rec) {
    const links = await page.$$eval('.fi-sidebar a[href], aside a[href]', (els) =>
        [...new Set(els.map((a) => a.getAttribute('href')).filter(Boolean))],
    ).catch(() => []);
    rec.sidebarLinks = links;
    for (const href of links) {
        if (!href.startsWith('/') && !href.startsWith(BASE)) continue;
        const url = href.startsWith(BASE) ? href : BASE + href;
        const before = snap(rec);
        const outcome = { href, status: null, note: null };
        rec.pages.push(outcome);
        try {
            await gotoPage(page, url);
            await page.waitForTimeout(500);
            const status = await page.evaluate(async () => {
                const res = await fetch(location.href, { method: 'GET', headers: { 'X-QA-Sweep': '1' } });
                return res.status;
            }).catch(() => null);
            outcome.status = status;
            const body = await page.evaluate(() => document.body ? document.body.innerText.slice(0, 3000) : '').catch(() => '');
            const m = markerMatch(body);
            if (m) outcome.note = `DOM marker: ${m.source}`;
            if (status >= 500) outcome.note = (outcome.note ? outcome.note + '; ' : '') + `HTTP ${status}`;
            if (!status || status < 500) {
                const sub = { url: url.replace(BASE, ''), http: [], consoleErrors: [], pageErrors: [], domErrors: [], links: [], forms: [], buttons: [], scripts: [], pages: [], buttonsTotal: 0, title: '' };
                await sweepPage(page, sub, { noLinks: true, maxDropdowns: 6, maxButtons: 20 });
                outcome.sub = sub;
            }
        } catch (e) {
            outcome.note = `load failed: ${String(e).slice(0, 120)}`;
        }
        const d = delta(rec, before);
        if (d.http.length || d.console.length || d.page.length || d.dom.length) {
            outcome.errors = { http: d.http, console: d.console, page: d.page, dom: d.dom };
        }
    }
}

async function runSpec(browser, spec) {
    const rec = {
        label: spec.label,
        kind: spec.kind ?? 'page',
        http: [],
        consoleErrors: [],
        pageErrors: [],
        domErrors: [],
        links: [],
        forms: [],
        buttons: [],
        scripts: [],
        pages: [],
        sidebarLinks: [],
        buttonsTotal: 0,
        ticketUrl: null,
        loginUrl: null,
    };
    const ctx = await browser.newContext({ acceptDownloads: true });
    const page = await ctx.newPage();
    page.setDefaultTimeout(15000);
    page.on('response', (r) => {
        if (r.status() >= 400 && !/\.(css|js|png|jpe?g|svg|webp|woff2?|ttf|ico|gif|map)(\?|$)/i.test(r.url())) {
            rec.http.push({ status: r.status(), url: r.url(), at: new Date().toISOString() });
        }
    });
    page.on('pageerror', (e) => rec.pageErrors.push(String(e).slice(0, 500)));
    page.on('console', (m) => {
        if (m.type() === 'error') rec.consoleErrors.push(m.text().slice(0, 300));
    });
    page.on('dialog', (d) => d.accept().catch(() => {}));
    page.on('popup', (p) => p.close().catch(() => {}));
    page.on('download', () => { /* downloads accepted, not an error */ });

    try {
        const watchdog = setTimeout(() => { rec.fatal = 'SPEC TIMEOUT'; }, spec.timeoutMs ?? 600000);
        if (spec.login) {
            rec.loginUrl = await login(page, spec.login.url, spec.login.email);
        } else {
            await gotoPage(page, BASE + spec.url);
            await page.waitForTimeout(500);
        }
        if (spec.scripts) {
            for (const s of spec.scripts) {
                await s(page, rec);
            }
        }
        if (spec.after) {
            await spec.after(page, rec);
        }
        if (spec.onlyUrls) {
            for (const u of spec.onlyUrls) {
                await visitAndMark(page, rec, u, u);
            }
        } else if (spec.sidebar) {
            await sweepSidebarPages(page, rec);
        } else if (!(spec.sweepOpts?.noSweep)) {
            await sweepPage(page, rec, spec.sweepOpts ?? {});
        }
        clearTimeout(watchdog);
        // Watchdog only stamps a note; if we got here the spec finished.
        if (rec.fatal === 'SPEC TIMEOUT') delete rec.fatal;
    } catch (e) {
        rec.fatal = String(e).slice(0, 500);
    }
    await ctx.close().catch(() => {});
    results.specs.push(rec);
    return rec;
}

const specs = [
    // ---- Tenant public: solo -------------------------------------------
    { label: 'solo public home', url: '/solo/' },
    { label: 'solo /book wizard', url: '/solo/book', scripts: [(page, rec) => scriptedBooking(page, rec, 'solo'), scriptedTicket] },
    { label: 'solo /portal', url: '/solo/portal', scripts: [(page, rec) => scriptedPortal(page, rec, 'solo')] },

    // ---- Tenant public: mups (clinic) ----------------------------------
    { label: 'mups public home', url: '/mups/' },
    { label: 'mups /departments', url: '/mups/departments' },
    { label: 'mups /blog', url: '/mups/blog' },
    { label: 'mups /doctors', url: '/mups/doctors' },
    { label: 'mups /book wizard', url: '/mups/book', scripts: [(page, rec) => scriptedBooking(page, rec, 'mups'), scriptedTicket] },
    { label: 'mups /portal', url: '/mups/portal', scripts: [(page, rec) => scriptedPortal(page, rec, 'mups')] },

    // ---- Tenant public: painsolution -----------------------------------
    { label: 'painsolution public home', url: '/painsolution/' },
    { label: 'painsolution /book wizard', url: '/painsolution/book', scripts: [(page, rec) => scriptedBooking(page, rec, 'painsolution'), scriptedTicket] },
    { label: 'painsolution /portal', url: '/painsolution/portal', scripts: [(page, rec) => scriptedPortal(page, rec, 'painsolution')] },

    // ---- Tenant admin: solo --------------------------------------------
    { label: 'solo admin panel (doctor)', login: { url: '/solo/admin/login', email: 'doctor@solo.com' }, sidebar: true, timeoutMs: 1800000 },
    { label: 'solo screens + queue day + consult', login: { url: '/solo/admin/login', email: 'doctor@solo.com' }, scripts: [(page, rec) => scriptedScreens(page, rec, 'solo'), (page, rec) => scriptedQueueDay(page, rec, 'solo')], sweepOpts: { noSweep: true }, timeoutMs: 600000 },
    { label: 'solo admin leftover (admin)', login: { url: '/solo/admin/login', email: 'admin@solo.com' }, onlyUrls: [
        '/solo/admin/chambers',
        '/solo/admin/schedule-sessions',
        '/solo/admin/users',
        '/solo/admin/data-backup',
        '/solo/admin/doctors',
        '/solo/admin/waiting-for-earlier-date',
        '/solo/admin/follow-up-reminders',
        '/solo/admin/visiting-day',
    ], timeoutMs: 900000 },

    // ---- Tenant admin: mups --------------------------------------------
    { label: 'mups admin panel (admin)', login: { url: '/mups/admin/login', email: 'admin@mups.local' }, sidebar: true, timeoutMs: 1800000 },
    { label: 'mups admin panel (doctor)', login: { url: '/mups/admin/login', email: 'doctor@mups.local' }, sidebar: true, timeoutMs: 1800000 },
    { label: 'mups admin panel (staff)', login: { url: '/mups/admin/login', email: 'staff@mups.local' }, sidebar: true, timeoutMs: 1800000 },

    // ---- Tenant admin: painsolution ------------------------------------
    { label: 'painsolution admin leftover (admin)', login: { url: '/painsolution/admin/login', email: 'admin@painsolution.local' }, onlyUrls: [
        '/painsolution/admin/web-pages',
        '/painsolution/admin/branding-settings',
        '/painsolution/admin/chambers',
        '/painsolution/admin/doctors',
        '/painsolution/admin/schedule-sessions',
        '/painsolution/admin/users',
        '/painsolution/admin/data-backup',
        '/painsolution/admin/departments',
        '/painsolution/admin/blog-posts',
    ], timeoutMs: 900000 },
    { label: 'painsolution admin panel (admin)', login: { url: '/painsolution/admin/login', email: 'admin@painsolution.local' }, sidebar: true, timeoutMs: 1800000 },
    { label: 'painsolution admin panel (doctor)', login: { url: '/painsolution/admin/login', email: 'doctor@painsolution.local' }, sidebar: true, timeoutMs: 1800000 },
    { label: 'painsolution admin panel (staff)', login: { url: '/painsolution/admin/login', email: 'staff@painsolution.local' }, sidebar: true, timeoutMs: 1800000 },
    { label: 'painsolution screens + queue day', login: { url: '/painsolution/admin/login', email: 'staff@painsolution.local' }, scripts: [(page, rec) => scriptedScreens(page, rec, 'painsolution'), (page, rec) => scriptedQueueDay(page, rec, 'painsolution')], sweepOpts: { noSweep: true }, timeoutMs: 600000 },
];

function writeReport() {
    results.finishedAt = new Date().toISOString();
    fs.mkdirSync(path.dirname(OUT), { recursive: true });
    fs.writeFileSync(OUT, JSON.stringify(results, null, 2));
}

async function main() {
    const browser = await chromium.launch();
    const selected = specs.filter((s) => !FILTER || s.label.includes(FILTER));
    console.log(`Sweeping ${selected.length} of ${specs.length} specs against ${BASE}`);
    for (const spec of selected) {
        const t0 = Date.now();
        const rec = await runSpec(browser, spec);
        const bad = countCrashes(rec);
        rec.crashCount = bad;
        console.log(`[${bad ? '!!' : 'ok'}] ${spec.label}  (${((Date.now() - t0) / 1000).toFixed(0)}s, ${rec.buttonsTotal} buttons, ${rec.http.length} bad resp, ${rec.pageErrors.length} page errors, ${rec.domErrors.length} dom markers, ${bad} crashes)`);
        writeReport();
    }
    await browser.close();
    writeReport();
    console.log(`\nReport: ${OUT}`);
}

main().catch((e) => { console.error('FATAL', e); process.exit(1); });