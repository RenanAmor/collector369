const path = require('path');
const fs = require('fs');
const { loadEnv } = require('../shared/env');
const { createPersistentContext } = require('../shared/browserContext');

const rootDir = path.resolve(__dirname, '..', '..');
loadEnv(rootDir);

/**
 * Ferramenta de diagnóstico temporária (não faz parte do fluxo oficial de
 * coleta). Investiga o comportamento real do botão de exportação da
 * Carteira: existência de menu intermediário, iframes, nova aba, download
 * direto vs. geração assíncrona.
 */
function collectExportCandidates(frame) {
    return frame.evaluate(() => {
        const isExportLike = (text) => /export/i.test(text || '');
        const nodes = Array.from(document.querySelectorAll('button, a, [role="button"], span, div'));
        const seen = new Set();
        const results = [];

        for (const node of nodes) {
            const text = (node.innerText || node.getAttribute('aria-label') || node.getAttribute('title') || '').trim();

            if (!isExportLike(text) || text.length > 60) {
                continue;
            }

            const rect = node.getBoundingClientRect();
            const key = `${node.tagName}|${text}|${Math.round(rect.x)}|${Math.round(rect.y)}`;

            if (seen.has(key)) {
                continue;
            }

            seen.add(key);

            results.push({
                tag: node.tagName,
                text,
                id: node.id || null,
                className: node.className || null,
                visible: rect.width > 0 && rect.height > 0,
                box: { x: rect.x, y: rect.y, width: rect.width, height: rect.height },
            });
        }

        return results;
    });
}

async function run() {
    const carteiraUrl = process.env.INVESTING_CARTEIRA_URL;
    const profileDir = path.resolve(rootDir, process.env.INVESTING_PROFILE_DIR || './storage/session/investing-profile');
    const outputDir = path.resolve(rootDir, 'storage', 'downloads', 'investing', 'diagnostico');

    if (!carteiraUrl) {
        throw new Error('INVESTING_CARTEIRA_URL não configurado no .env');
    }

    if (!fs.existsSync(profileDir)) {
        throw new Error(`Perfil de navegador não encontrado em ${profileDir}. Rode "npm run investing:login" primeiro.`);
    }

    fs.mkdirSync(outputDir, { recursive: true });

    const context = await createPersistentContext({ headless: false, profileDir });

    const report = {
        timestamp: new Date().toISOString(),
        carteiraUrl,
        iframes: [],
        exportCandidatesMainFrame: [],
        exportCandidatesIframes: [],
        newTabOpened: false,
        newTabUrl: null,
        downloadDetected: false,
        downloadFilename: null,
        clickedCandidate: null,
        errors: [],
    };

    context.on('page', (newPage) => {
        report.newTabOpened = true;
        report.newTabUrl = newPage.url();
    });

    try {
        const page = await context.newPage();

        page.on('download', (download) => {
            report.downloadDetected = true;
            report.downloadFilename = download.suggestedFilename();
        });

        await page.goto(carteiraUrl, { waitUntil: 'networkidle', timeout: 60000 });
        await page.waitForTimeout(3000);

        await page.screenshot({ path: path.join(outputDir, '01-antes-do-clique.png'), fullPage: true });

        report.iframes = page.frames()
            .filter((frame) => frame !== page.mainFrame())
            .map((frame) => ({ url: frame.url(), name: frame.name() }));

        report.exportCandidatesMainFrame = await collectExportCandidates(page.mainFrame());

        for (const frame of page.frames()) {
            if (frame === page.mainFrame()) {
                continue;
            }

            try {
                const candidates = await collectExportCandidates(frame);

                if (candidates.length > 0) {
                    report.exportCandidatesIframes.push({ frameUrl: frame.url(), candidates });
                }
            } catch (frameError) {
                report.errors.push(`Falha ao inspecionar iframe ${frame.url()}: ${frameError.message}`);
            }
        }

        const target = report.exportCandidatesMainFrame.find((candidate) => candidate.visible);

        if (target) {
            report.clickedCandidate = target;

            try {
                await page.locator(`text=${target.text}`).first().click({ timeout: 10000 });
            } catch (clickError) {
                report.errors.push(`Falha ao clicar no candidato: ${clickError.message}`);
            }

            await page.waitForTimeout(5000);
            await page.screenshot({ path: path.join(outputDir, '02-depois-do-clique.png'), fullPage: true }).catch(() => {});
        } else {
            report.errors.push('Nenhum elemento visível com texto parecido com "export" foi encontrado no frame principal.');
        }

        fs.writeFileSync(path.join(outputDir, 'relatorio.json'), JSON.stringify(report, null, 2));

        return outputDir;
    } finally {
        await context.close();
    }
}

run()
    .then((outputDir) => {
        console.log(`Diagnóstico concluído. Arquivos em: ${outputDir}`);
    })
    .catch((error) => {
        console.error('Falha no diagnóstico:', error.message);
        process.exitCode = 1;
    });
