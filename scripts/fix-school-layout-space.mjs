import fs from 'fs';
import path from 'path';

function walk(dir, files = []) {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        const full = path.join(dir, entry.name);
        if (entry.isDirectory()) walk(full, files);
        else if (entry.name.endsWith('.vue')) files.push(full);
    }
    return files;
}

let fixed = 0;
for (const file of walk('resources/js/Pages/Admin/School')) {
    let content = fs.readFileSync(file, 'utf8');
    const next = content.replace(/<SchoolAdminLayout(?=[a-z:`])/g, '<SchoolAdminLayout ');
    if (next !== content) {
        fs.writeFileSync(file, next);
        fixed++;
    }
}
console.log(`Fixed ${fixed} files.`);
