export function addCalendarMonths(isoDate, durationMonths) {
    const match = String(isoDate ?? '').match(/^(\d{4})-(\d{2})-(\d{2})$/);
    const months = Number(durationMonths);

    if (!match || !Number.isInteger(months) || months < 1) return '';

    const [, year, month, day] = match.map(Number);
    const sourceDate = new Date(year, month - 1, day);

    if (
        sourceDate.getFullYear() !== year
        || sourceDate.getMonth() !== month - 1
        || sourceDate.getDate() !== day
    ) {
        return '';
    }

    const targetMonth = new Date(year, month - 1 + months, 1);
    const lastDayOfTargetMonth = new Date(
        targetMonth.getFullYear(),
        targetMonth.getMonth() + 1,
        0,
    ).getDate();
    targetMonth.setDate(Math.min(day, lastDayOfTargetMonth));

    const targetYear = String(targetMonth.getFullYear()).padStart(4, '0');
    const targetMonthNumber = String(targetMonth.getMonth() + 1).padStart(2, '0');
    const targetDay = String(targetMonth.getDate()).padStart(2, '0');

    return `${targetYear}-${targetMonthNumber}-${targetDay}`;
}
