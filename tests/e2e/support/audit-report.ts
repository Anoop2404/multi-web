import * as fs from 'fs';
import * as path from 'path';
import type { UxIssue } from './ux';

export type PageAuditResult = {
    area: string;
    path: string;
    label: string;
    url: string;
    httpStatus: number;
    durationMs: number;
    issues: UxIssue[];
    consoleErrors: string[];
};

export type FullUxAuditReport = {
    generatedAt: string;
    summary: {
        pagesVisited: number;
        errors: number;
        warnings: number;
        info: number;
        consoleErrors: number;
    };
    pages: PageAuditResult[];
    failures: { area: string; label: string; issues: UxIssue[] }[];
};

const reportDir = path.join(process.cwd(), 'storage', 'app', 'ux-audit');
const pagesFile = path.join(reportDir, 'ux-audit-pages.json');

let pages: PageAuditResult[] = [];

export function resetAuditReport(): void {
    pages = [];
    if (fs.existsSync(pagesFile)) {
        fs.unlinkSync(pagesFile);
    }
}

export function recordPageResult(result: PageAuditResult): void {
    pages.push(result);
    fs.mkdirSync(reportDir, { recursive: true });
    fs.writeFileSync(pagesFile, JSON.stringify(pages, null, 2));
}

export function buildFullReport(): FullUxAuditReport {
    if (pages.length === 0 && fs.existsSync(pagesFile)) {
        pages = JSON.parse(fs.readFileSync(pagesFile, 'utf8')) as PageAuditResult[];
    }

    const allIssues = pages.flatMap((p) => p.issues);
    const consoleErrors = pages.reduce((n, p) => n + p.consoleErrors.length, 0);

    const failures = pages
        .filter((p) => p.issues.some((i) => i.severity === 'error'))
        .map((p) => ({
            area: p.area,
            label: p.label,
            issues: p.issues.filter((i) => i.severity === 'error'),
        }));

    return {
        generatedAt: new Date().toISOString(),
        summary: {
            pagesVisited: pages.length,
            errors: allIssues.filter((i) => i.severity === 'error').length,
            warnings: allIssues.filter((i) => i.severity === 'warning').length,
            info: allIssues.filter((i) => i.severity === 'info').length,
            consoleErrors,
        },
        pages,
        failures,
    };
}

export function writeFullReport(): FullUxAuditReport {
    const report = buildFullReport();
    fs.mkdirSync(reportDir, { recursive: true });
    fs.writeFileSync(path.join(reportDir, 'ux-audit.json'), JSON.stringify(report, null, 2));

    const md = renderMarkdown(report);
    fs.writeFileSync(path.join(reportDir, 'ux-audit.md'), md);

    return report;
}

function renderMarkdown(report: FullUxAuditReport): string {
    const lines: string[] = [
        '# UX Audit Report',
        '',
        `Generated: ${report.generatedAt}`,
        '',
        '## Summary',
        '',
        `| Metric | Count |`,
        `|--------|-------|`,
        `| Pages visited | ${report.summary.pagesVisited} |`,
        `| Errors | ${report.summary.errors} |`,
        `| Warnings | ${report.summary.warnings} |`,
        `| Info | ${report.summary.info} |`,
        `| Console errors | ${report.summary.consoleErrors} |`,
        '',
    ];

    if (report.failures.length > 0) {
        lines.push('## Failures (P0/P1)', '');
        for (const f of report.failures) {
            lines.push(`### ${f.area} — ${f.label}`, '');
            for (const issue of f.issues) {
                lines.push(`- **[${issue.code}]** ${issue.message}`);
                lines.push(`  - ${issue.url}`);
            }
            lines.push('');
        }
    }

    const warnings = report.pages.flatMap((p) =>
        p.issues
            .filter((i) => i.severity === 'warning')
            .map((i) => ({ page: p.label, issue: i })),
    );
    if (warnings.length > 0) {
        lines.push('## Warnings', '');
        for (const { page, issue } of warnings.slice(0, 50)) {
            lines.push(`- **${page}** [${issue.code}]: ${issue.message}`);
        }
        if (warnings.length > 50) {
            lines.push(`- … and ${warnings.length - 50} more`);
        }
        lines.push('');
    }

    return lines.join('\n');
}

export function getCriticalIssues(): UxIssue[] {
    return pages.flatMap((p) => p.issues.filter((i) => i.severity === 'error'));
}
