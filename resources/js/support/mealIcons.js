// Canonical meal-type icons, shared by the food menu builder, the school ordering
// page, and both day-wise order reports so a meal reads the same everywhere.
export const MEAL_ICONS = { breakfast: '🌅', lunch: '🍽️', snacks: '🍪', tea: '☕', dinner: '🌙', other: '🍴' };

export function mealIcon(type) {
    return MEAL_ICONS[type] || '🍴';
}
