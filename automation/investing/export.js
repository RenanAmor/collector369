const path = require('path');
const fs = require('fs');
const { loadEnv } = require('../shared/env');
const { createPersistentContext } = require('../shared/browserContext');

const rootDir = path.resolve(__dirname, '..', '..');
loadEnv(rootDir);

async function run() {
    const carteiraUrl = process.env.INVESTING_CARTEIRA_URL;
    const exportSelector = process.env.INVESTING_EXPORT_SELECTOR || 'text=Exportar';
    const headless = (process.env.BROWSER_HEADLESS || 'true') !== 'false';
    const profileDir = path.resolve(rootDir, process.env.INVESTING_PROFILE_DIR || './storage/session/investing-profile');
    const downloadDir = path.resolve(rootDir, 'storage', 'downloads', 'investing');

    if (!carteiraUrl) {
        throw new Error('INVESTING_CARTEIRA_URL não configurado no .env');
    }

    if (!fs.existsSync(profileDir)) {
        throw new Error(`Perfil de navegador não encontrado em ${profileDir}. Rode "npm run investing:login" primeiro.`);
    }

    fs.mkdirSync(downloadDir, { recursive: true });

    const context = await createPersistentContext({ headless, profileDir });

    try {
        const page = await context.newPage();
        await page.goto(carteiraUrl, { waitUntil: 'networkidle' });

        const [download] = await Promise.all([
            page.waitForEvent('download'),
            page.locator(exportSelector).first().click(),
        ]);

        const suggestedName = download.suggestedFilename();
        const destination = path.join(downloadDir, `${Date.now()}_${suggestedName}`);
        await download.saveAs(destination);

        return destination;
    } finally {
        await context.close();
    }
}

run()
    .then((filePath) => {
        process.stdout.write(JSON.stringify({
            success: true,
            filePath,
            timestamp: new Date().toISOString(),
            error: null,
        }) + '\n');
    })
    .catch((error) => {
        process.stdout.write(JSON.stringify({
            success: false,
            filePath: null,
            timestamp: new Date().toISOString(),
            error: error.message,
        }) + '\n');
        process.exitCode = 1;
    });
