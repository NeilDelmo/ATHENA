export function formatPersonName(value) {
    return String(value ?? '').trim().replace(/\s+/g, ' ');
}

export function personInitials(value) {
    const parts = formatPersonName(value).split(/\s+/).filter(Boolean);

    if (parts.length === 0) return '?';
    if (parts.length === 1) return parts[0].slice(0, 1).toLocaleUpperCase();

    return `${parts[0][0]}${parts.at(-1)[0]}`.toLocaleUpperCase();
}

export function filterWorkspacePeople(people, query) {
    const search = String(query ?? '').trim().toLocaleLowerCase();

    if (!search) return people;

    return people.filter((person) => (
        formatPersonName(person.name).toLocaleLowerCase().includes(search)
        || String(person.email ?? '').toLocaleLowerCase().includes(search)
    ));
}
