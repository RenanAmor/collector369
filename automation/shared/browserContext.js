const { chromium } = require('playwright');

/**
 * Abre um contexto persistente usando o Chrome real instalado na máquina
 * (não o Chromium embutido do Playwright). Isso aproxima o fingerprint da
 * automação do de um uso manual comum, reduzindo bloqueios de proteção
 * anti-bot de sites como o Investing.com. O próprio diretório de perfil
 * guarda a sessão (cookies/local storage) entre execuções.
 */
async function createPersistentContext({ headless = true, profileDir }) {
    return chromium.launchPersistentContext(profileDir, {
        headless,
        channel: 'chrome',
    });
}

module.exports = { createPersistentContext };
