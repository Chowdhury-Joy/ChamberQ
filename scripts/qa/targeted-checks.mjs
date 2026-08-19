#!/usr/bin/env node
/**
 * One-shot checks the blind sweep cannot prove:
 * - Patient OTP login then a single Log out (fresh session)
 * - Solo Live Queue "Copy link" (clipboard may fail headless — we still watch for 5xx)
 */
import { chromium } from 'playwright';
import fs from 'node:fs';

const BASE = process.env.QA_BASE_URL ?? 'http://127.0.0.1:8000';
const PASSWORD = 'pass';
const QA_PHONE = '017' + String(Math.floor(10000000 + Math.random() * 89999999));

const MARKERS = [
    /Internal Server Error/i,
    /SQLSTATE/i,
    /Connection\.php:\d+/,
    /Something went wrong\./,
    /This form expired\./,
];

function markerMatch(text) {
    return MARKERS.find((re) => re.test(text)) ?? null;
}

async function lastOtpCode() {
    const log = fs.readFileSync('storage/logs/laravel.log', 'utf8');
    const m = [...log.matchAll(/ChamberQ code: (\d{6})/g)];
    return m.length ? m[m.length - 1][1] : null;
}

const results = { startedAt: new Date().toISOString(), base: BASE, checks: [] };

async function checkPatientLogout(page) {
    const rec = { name: 'patient OTP then Log out', ok: true, steps: [], http: [] };
    page.on('response', (r) => {
        if (r.status() >= 400 && r.url().includes(BASE.replace('http://', ''))) {
            rec.http.push({ status: r.status(), url: r.url() });
        }
    });
    try {
        await page.goto(`${BASE}/me/login`, { waitUntil: 'domcontentloaded' });
        await page.locator('input[name="phone"], input[type="tel"]').first().fill(QA_PHONE);
        await Promise.all([
            page.waitForResponse((r) => r.url().includes('/me/login/otp'), { timeout: 10000 }).catch(() => null),
            page.locator('form button[type="submit"], form button').first().click(),
        ]);
        await page.waitForTimeout(800);
        const code = await lastOtpCode();
        if (!code) {
            rec.ok = false;
            rec.steps.push('fail: no OTP in log');
            return rec;
        }
        rec.steps.push('send-otp');
        await page.locator('#code').fill(code);
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 15000 }).catch(() => null),
            page.locator('form[action*="verify"] button').first().click(),
        ]);
        await page.waitForTimeout(600);
        if (!page.url().includes('/me')) {
            rec.ok = false;
            rec.steps.push(`fail: after verify url=${page.url()}`);
            return rec;
        }
        rec.steps.push('verify-otp');

        const logoutBtn = page.locator('form.pf-logout button[type="submit"], form[action="/me/logout"] button').first();
        const [resp] = await Promise.all([
            page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 10000 }).catch(() => null),
            logoutBtn.click(),
        ]);
        await page.waitForTimeout(500);
        const body = await page.evaluate(() => document.body?.innerText?.slice(0, 2000) ?? '');
        const m = markerMatch(body);
        const bad = rec.http.filter((h) => h.status >= 500 || h.status === 419);
        if (m || bad.length) {
            rec.ok = false;
            rec.steps.push(`fail: marker=${m?.source ?? 'none'} badHttp=${JSON.stringify(bad)}`);
        } else {
            rec.steps.push(`logout ok url=${page.url()}`);
        }
    } catch (e) {
        rec.ok = false;
        rec.steps.push(`error: ${String(e).slice(0, 200)}`);
    }
    return rec;
}

async function checkCopyLink(page) {
    const rec = { name: 'solo Live Queue Copy link', ok: true, steps: [], http: [] };
    page.on('response', (r) => {
        if (r.status() >= 500) rec.http.push({ status: r.status(), url: r.url() });
    });
    try {
        await page.goto(`${BASE}/solo/admin/login`, { waitUntil: 'domcontentloaded' });
        await page.locator('#form\\.email, input[name="email"]').first().fill('doctor@solo.com');
        await page.locator('#form\\.password, input[name="password"]').first().fill(PASSWORD);
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 20000 }).catch(() => null),
            page.locator('button[type="submit"]').first().click(),
        ]);
        await page.waitForTimeout(1200);
        rec.steps.push('login');

        await page.goto(`${BASE}/solo/admin/live-queue-control`, { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(1500);
        const card = page.locator('.lqc-session-card').first();
        if (await card.count()) {
            await card.click();
            await page.waitForTimeout(1000);
            rec.steps.push('pick-session');
        }

        const copyBtn = page.locator('button:has-text("Copy link")').first();
        if (!(await copyBtn.count())) {
            rec.ok = false;
            rec.steps.push('fail: no Copy link button (no live session today?)');
            return rec;
        }
        await copyBtn.click();
        await page.waitForTimeout(400);
        const body = await page.evaluate(() => document.body?.innerText?.slice(0, 2000) ?? '');
        const m = markerMatch(body);
        if (m || rec.http.length) {
            rec.ok = false;
            rec.steps.push(`fail: marker=${m?.source ?? 'none'} http=${JSON.stringify(rec.http)}`);
        } else {
            rec.steps.push('copy clicked — no 5xx on page (clipboard may still be blocked headless)');
        }
    } catch (e) {
        rec.ok = false;
        rec.steps.push(`error: ${String(e).slice(0, 200)}`);
    }
    return rec;
}

async function main() {
    const browser = await chromium.launch();
    const ctx = await browser.newContext({ acceptDownloads: true });
    const page = await ctx.newPage();
    results.checks.push(await checkPatientLogout(page));
    results.checks.push(await checkCopyLink(page));
    await browser.close();
    results.finishedAt = new Date().toISOString();
    const out = process.argv[2] ?? 'storage/qa-targeted-checks.json';
    fs.writeFileSync(out, JSON.stringify(results, null, 2));
    for (const c of results.checks) {
        console.log(`${c.ok ? 'ok' : 'FAIL'} | ${c.name} | ${c.steps.join(' → ')}`);
    }
    console.log(`\nReport: ${out}`);
}

main().catch((e) => { console.error(e); process.exit(1); });
