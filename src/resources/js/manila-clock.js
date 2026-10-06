export function createManilaClock(documentRoot = document, timers = window) {
    let interval;
    const formatter = new Intl.DateTimeFormat('en-PH', {
        timeZone: 'Asia/Manila',
        month: 'short',
        day: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true,
    });

    return () => {
        if (interval !== undefined) timers.clearInterval(interval);
        interval = undefined;
        const clock = documentRoot.getElementById('manila-system-time');
        if (!clock) return;

        const update = () => {
            const now = new Date();
            clock.textContent = formatter.format(now).replace(',', ' |');
            clock.dateTime = now.toISOString();
        };

        update();
        interval = timers.setInterval(update, 1000);
    };
}
