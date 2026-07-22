const path = require('path');
const fs = require('fs');
const { loadEnv } = require('../shared/env');
const { createPersistentContext } = require('../shared/browserContext');

const rootDir = path.resolve(__dirname, '..', '..');
loadEnv(rootDir);

async function waitForEnter() {
    return new Promise((resolve) => {
        process.stdin.resume();
        process.stdin.once('data', () => {
            process.stdin.pause();
            resolve();
        });
    });
}

async function main() {
    const carteiraUrl = process.env.INVESTING_CARTEIRA_URL || process.env.INVESTING_BASE_URL;

    if (!carteiraUrl) {
        throw new Error('INVESTING_CARTEIRA_URL (ou INVESTING_BASE_URL) não configurado no .env');
    }

    const profileDir = path.resolve(rootDir, process.env.INVESTING_PROFILE_DIR || './storage/session/investing-profile');
    fs.mkdirSync(profileDir, { recursive: true });

    const context = await createPersistentContext({ headless: false, profileDir });
    const page = await context.newPage();

    await page.goto(carteiraUrl);

    console.log('Faça login manualmente no Investing.com nesta janela do navegador (Chrome real).');
    console.log('Depois de concluir o login (resolvendo captcha/2FA se aparecer) e estar na página da Carteira, volte a este terminal e pressione ENTER.');

    await waitForEnter();

    console.log(`Perfil de navegador salvo em: ${profileDir}`);

    await context.close();
}

main().catch((error) => {
    console.error('Falha no login manual:', error.message);
    process.exitCode = 1;
});
