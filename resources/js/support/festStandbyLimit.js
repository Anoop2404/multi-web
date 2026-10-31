export function standbyLimit(item, eventType) {
    if (eventType !== 'sports') return 2;
    return Math.max(0, Number(item?.criteria_json?.standbys ?? item?.standbys ?? item?.squad_rules?.standbys ?? 1));
}
