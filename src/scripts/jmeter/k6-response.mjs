export function responseError(status, contentType, body, operation, user) {
    if (status !== 200) return `Unexpected HTTP ${status}`;
    if (operation.binary) return !body || /html/i.test(contentType) ? 'Expected a nonempty document' : null;
    if (/<(?:a|form)\b[^>]*(?:href|action)=["'][^"']*\/(?:auth\/google|login)["']/i.test(body)) return 'Sign-in page returned';
    const attributes = body.match(/<body\b[^>]*>/i)?.[0] ?? '';
    if (attributes.match(/data-active-workspace=["']([^"']+)["']/)?.[1] !== user.workspace) return 'Wrong workspace';
    if (user.actorId && attributes.match(/data-auth-user-id=["']([^"']+)["']/)?.[1] !== String(user.actorId)) return 'Wrong account';
    return null;
}
