import fs from 'fs';
import path from 'path';

const root = 'resources/js/Pages/Admin/School';

const eyebrowRules = [
    { test: /^(Students|Teachers|Houses|Setup|Users)\//, eyebrow: 'Students', desc: 'Student records, teachers, and portal access.' },
    { test: /^Registration\//, eyebrow: 'Membership', desc: 'Annual Sahodaya membership registration and school profile.' },
    { test: /^(Events|Fest|Mcq|Training|Circulars|Payments|Notifications)\//, eyebrow: 'Programs', desc: 'Fest programs, exams, training, and Sahodaya circulars.' },
    { test: /^(News|Gallery|Staff|Achievements|Downloads|JobVacancies|BoardResults|Alumni|Testimonials|Contact|SiteBuilder|Enquiries)\//, eyebrow: 'Website', desc: 'School website content and public pages.' },
    { test: /^Settings\//, eyebrow: 'School', desc: 'School profile and configuration.' },
    { test: /^Dashboard\.vue$/, eyebrow: 'Dashboard', desc: 'Manage students and complete annual Sahodaya membership registration.' },
];

function metaFor(rel) {
    for (const rule of eyebrowRules) {
        if (rule.test.test(rel)) {
            return { eyebrow: rule.eyebrow, description: rule.desc };
        }
    }
    return { eyebrow: 'School', description: '' };
}

function walk(dir, files = []) {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        const full = path.join(dir, entry.name);
        if (entry.isDirectory()) walk(full, files);
        else if (entry.name.endsWith('.vue')) files.push(full);
    }
    return files;
}

let updated = 0;
let skipped = 0;

for (const file of walk(root)) {
    let content = fs.readFileSync(file, 'utf8');
    if (content.includes('<PageHeader')) {
        skipped++;
        continue;
    }

    const layoutMatch = content.match(/<SchoolAdminLayout([^>]*)>/s);
    if (!layoutMatch) continue;

    let layoutAttrs = layoutMatch[1];
    const staticTitle = layoutAttrs.match(/\btitle="([^"]+)"/);
    const dynamicTitle = layoutAttrs.match(/\b:title="([^"]+)"/);

    if (!staticTitle && !dynamicTitle) continue;

    if (!layoutAttrs.includes('show-header-title')) {
        layoutAttrs = `${layoutAttrs.trim()} :show-header-title="false"`;
    }

    const newLayout = `<SchoolAdminLayout ${layoutAttrs.trim()}>`;
    content = content.replace(/<SchoolAdminLayout[^>]*>/s, newLayout);

    const rel = path.relative(root, file);
    const { eyebrow, description } = metaFor(rel);
    const titleAttr = dynamicTitle
        ? `:title="${dynamicTitle[1]}"`
        : `title="${staticTitle[1]}"`;
    const descAttr = description ? `\n            description="${description}"` : '';
    const header = `        <PageHeader ${titleAttr} eyebrow="${eyebrow}"${descAttr} />\n\n`;

    content = content.replace(newLayout, `${newLayout}\n${header}`);
    fs.writeFileSync(file, content);
    updated++;
    console.log('Updated:', rel);
}

console.log(`Done. Updated ${updated}, skipped ${skipped}.`);
