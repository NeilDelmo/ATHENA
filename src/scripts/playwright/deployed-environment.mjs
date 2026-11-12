import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { baseUrl, workspaces } from '../jmeter/scenarios.mjs';

export { workspaces };

export function deployedURL(value = process.env.PLAYWRIGHT_DEPLOYED_URL ?? baseUrl) {
    const url = new URL(value);
    if (url.protocol !== 'https:' || url.username || url.password || url.pathname !== '/'
        || url.search || url.hash || ['localhost', '127.0.0.1', '[::1]'].includes(url.hostname)) {
        throw new Error('Deployed browser tests require an HTTPS website origin, without a path or credentials.');
    }
    return `${url.origin}/`;
}

export function sessionPath() {
    return resolve('playwright/.auth/deployed/account.json');
}

export function savedSession(target = deployedURL(), path = sessionPath()) {
    if (!existsSync(path)) return null;
    const state = JSON.parse(readFileSync(path, 'utf8'));
    const host = new URL(target).hostname;
    const cookies = state.cookies?.filter(cookie => {
        const domain = cookie.domain?.replace(/^\./, '');
        return domain && (host === domain || host.endsWith(`.${domain}`));
    });
    if (!cookies?.some(cookie => /session/i.test(cookie.name))) {
        throw new Error('The saved account sign-in does not belong to this deployed website. Capture it again.');
    }
    return { cookies, origins: [] };
}
