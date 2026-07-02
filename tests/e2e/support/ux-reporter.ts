import type {
    FullConfig,
    FullResult,
    Reporter,
    Suite,
    TestCase,
    TestResult,
} from '@playwright/test/reporter';
import * as fs from 'fs';
import * as path from 'path';
import { buildFullReport } from './audit-report';

type UxReport = {
    generatedAt: string;
    summary: { passed: number; failed: number; warnings: number };
    warnings: string[];
    failures: { title: string; errors: string[] }[];
    pageAudit?: ReturnType<typeof buildFullReport>;
};

class UxAuditReporter implements Reporter {
    private warnings: string[] = [];
    private failures: { title: string; errors: string[] }[] = [];
    private passed = 0;

    onTestEnd(test: TestCase, result: TestResult): void {
        if (result.status === 'passed') {
            this.passed++;
        }

        for (const annotation of result.annotations) {
            if (annotation.type === 'ux-warnings' && annotation.description) {
                this.warnings.push(`${test.title}: ${annotation.description}`);
            }
        }

        if (result.status === 'failed' && result.error) {
            this.failures.push({
                title: test.title,
                errors: [result.error.message ?? 'Unknown error'],
            });
        }
    }

    onEnd(result: FullResult): void {
        const outDir = path.join(process.cwd(), 'storage', 'app', 'ux-audit');
        fs.mkdirSync(outDir, { recursive: true });

        let pageAudit: ReturnType<typeof buildFullReport> | undefined;
        const pagesFile = path.join(outDir, 'ux-audit-pages.json');
        if (fs.existsSync(pagesFile)) {
            pageAudit = buildFullReport();
            if ((pageAudit.summary.pagesVisited ?? 0) > 0) {
                fs.writeFileSync(path.join(outDir, 'ux-audit.json'), JSON.stringify(pageAudit, null, 2));
            }
        }

        const report: UxReport = {
            generatedAt: new Date().toISOString(),
            summary: {
                passed: this.passed,
                failed: this.failures.length,
                warnings: this.warnings.length,
            },
            warnings: this.warnings,
            failures: this.failures,
            pageAudit,
        };

        fs.writeFileSync(path.join(outDir, 'ux-audit-runner.json'), JSON.stringify(report, null, 2));

        if (pageAudit) {
            console.log(
                `\n[ux-audit] ${pageAudit.summary.pagesVisited} pages visited — ` +
                    `${pageAudit.summary.errors} error(s), ${pageAudit.summary.warnings} warning(s)`,
            );
            console.log(`[ux-audit] Full report: storage/app/ux-audit/ux-audit.json`);
            console.log(`[ux-audit] Markdown:   storage/app/ux-audit/ux-audit.md`);
        } else if (this.warnings.length > 0) {
            console.log(`\n[ux-audit] ${this.warnings.length} warning(s) — see tests/e2e/report/ux-audit-runner.json`);
        }

        void result;
    }

    onBegin(config: FullConfig, suite: Suite): void {
        void config;
        void suite;
    }
}

export default UxAuditReporter;
