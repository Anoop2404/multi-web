// Helper to classify food items as veg or non-veg based on common culinary keywords.
// Standard in Indian food menus (green square dot for veg, red square dot for non-veg).

const NON_VEG_PATTERNS = [
    /\b(chicken|beef|mutton|meat|fish|egg|eggs|pork|prawn|prawns|crab|non-?veg)\b/i,
];

export function isNonVeg(name, description = '') {
    const text = `${name || ''} ${description || ''}`.toLowerCase();
    
    // Explicit veg overrides (e.g. "veg biriyani", "eggless")
    if (/\b(veg|vegetarian|pure veg)\b/i.test(name) && !/\b(non-?veg|chicken|beef|mutton|fish|pork)\b/i.test(name)) {
        return false;
    }

    return NON_VEG_PATTERNS.some(pattern => pattern.test(text));
}

export function dietType(name, description = '') {
    return isNonVeg(name, description) ? 'non-veg' : 'veg';
}
