const path = require('path');
const fs = require('fs');
const { loadEnv } = require('../shared/env');
const { createPersistentContext } = require('../shared/browserContext');

const rootDir = path.resolve(__dirname, '..', '..');
loadEnv(rootDir);

/**
 * Ferramenta de diagnóstico temporária (não faz parte do fluxo oficial).
 * Investiga:
 *  - se o diretório de perfil persistente realmente acumula dados entre execuções
 *    (ou seja, se a "sessão salva" está de fato sendo reaproveitada, não recriada);
 *  - sinais concretos de automação expostos pelo navegador nesse mesmo contexto
 *    (navigator.webdriver e afins), usando um site neutro de teste de fingerprint
 *    (bot.sannysoft.com) — não o Investing.com.
 */
async function run() {
    const profileDir = path.resolve(rootDir, process.env.INVESTING_PROFILE_DIR || './storage/session/investing-profile');
    const outputDir = path.resolve(rootDir, 'storage', 'downloads', 'investing', 'diagnostico');
    fs.mkdirSync(outputDir, { recursive: true });

    const report = {
        timestamp: new Date().toISOString(),
        profileDir,
        profileDirExists: fs.existsSync(profileDir),
    };

    if (report.profileDirExists) {
        const stat = fs.statSync(profileDir);
        const entries = fs.readdirSync(profileDir);

        report.profileDirEntryCount = entries.length;
        report.profileDirModified = stat.mtime.toISOString();
        report.profileDirSampleEntries = entries.slice(0, 15);
    }

    const context = await createPersistentContext({ headless: false, profileDir });

    try {
        const page = await context.newPage();

        report.automationSignals = await page.evaluate(() => ({
            webdriver: navigator.webdriver,
            userAgent: navigator.userAgent,
            languages: navigator.languages,
            pluginsCount: navigator.plugins.length,
            platform: navigator.platform,
            hasChrome: typeof window.chrome !== 'undefined',
            permissionsQueryExists: typeof navigator.permissions?.query === 'function',
        }));

        try {
            await page.goto('https://bot.sannysoft.com', { waitUntil: 'networkidle', timeout: 30000 });
            await page.waitForTimeout(2000);
            await page.screenshot({ path: path.join(outputDir, '03-fingerprint-sannysoft.png'), fullPage: true });
        } catch (navigationError) {
            report.sannysoftError = navigationError.message;
        }

        fs.writeFileSync(path.join(outputDir, 'relatorio-fingerprint.json'), JSON.stringify(report, null, 2));

        return outputDir;
    } finally {
        await context.close();
    }
}

run()
    .then((outputDir) => {
        console.log(`Diagnóstico de fingerprint concluído. Arquivos em: ${outputDir}`);
    })
    .catch((error) => {
        console.error('Falha no diagnóstico de fingerprint:', error.message);
        process.exitCode = 1;
    });
