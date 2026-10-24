import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';

const require = createRequire(import.meta.url);
const { getAllSigns } = require('@iso-safety-signs/core');
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const { version } = JSON.parse(fs.readFileSync(path.join(root, 'node_modules/@iso-safety-signs/core/package.json'), 'utf8'));
const catalogPath = path.join(root, 'resources/iso7010-catalog.json');
const catalog = JSON.parse(fs.readFileSync(catalogPath, 'utf8'));
const signs = getAllSigns();
const expectedCategories = { emergency: 73, fire: 19, mandatory: 72, prohibition: 81, warning: 87 };

function assert(condition, message) {
    if (!condition) throw new Error(message);
}

function assertSafeSvg(sign) {
    const svg = sign.svg;
    assert(/^<svg\b/i.test(svg) && /\bviewBox\s*=/.test(svg), `${sign.code}: SVG o viewBox ausente.`);
    assert(!/<(?:script|foreignObject|iframe|image|audio|video|canvas)\b/i.test(svg), `${sign.code}: contenido activo o externo.`);
    assert(!/\son[a-z]+\s*=/i.test(svg), `${sign.code}: manejador de eventos.`);
    assert(!/javascript\s*:/i.test(svg), `${sign.code}: URL ejecutable.`);
    assert(!/\b(?:href|src)\s*=\s*(['"])(?!#)/i.test(svg), `${sign.code}: referencia externa.`);
    assert(!/@import|url\(\s*['"]?(?:https?:|\/\/|\/)/i.test(svg), `${sign.code}: recurso CSS externo.`);
}

assert(catalog.format === 1 && catalog.standard === 'ISO 7010', 'Catálogo ISO 7010 inválido.');
assert(signs.length === 332 && Object.keys(catalog.signs).length === 332, 'El catálogo debe contener exactamente 332 señales registradas.');
const codes = new Set(signs.map(sign => sign.code));
assert(codes.size === signs.length, 'Hay códigos ISO duplicados.');
for (const [category, count] of Object.entries(expectedCategories)) {
    assert(signs.filter(sign => sign.category === category).length === count, `Conteo inesperado en ${category}.`);
    assert(catalog.categories[category], `Falta la categoría ${category}.`);
}
for (const code of Object.keys(catalog.signs)) assert(codes.has(code), `Traducción sin SVG: ${code}.`);

const output = {};
const notice = [
    'NORMIA SST · ATRIBUCIÓN DE PICTOGRAMAS ISO 7010',
    '=================================================',
    '',
    `Fuente de compilación: @iso-safety-signs/core ${version} (MIT © Karl Norling).`,
    'Proyecto: https://github.com/karlnorling/iso-safety-signs',
    'La licencia MIT del paquete se incluye como iso-safety-signs.LICENSE.txt.',
    '',
    'Los gráficos de señales ISO 7010 fueron publicados originalmente bajo licencias',
    'libres en Wikimedia Commons. La licencia y autoría de cada dibujo pueden variar;',
    'consulte la página fuente enlazada antes de reutilizar un pictograma por separado.',
    'Las traducciones y descripciones en español son apoyo de interfaz y no sustituyen',
    'la norma, la evaluación del centro ni la revisión de una persona competente.',
    ''
];

for (const sign of signs) {
    const translated = catalog.signs[sign.code];
    assert(translated, `Falta traducción para ${sign.code}.`);
    assert(translated.category === sign.category, `Categoría inconsistente en ${sign.code}.`);
    assert(translated.name.trim() && translated.description.trim(), `Texto incompleto en ${sign.code}.`);
    assertSafeSvg(sign);
    output[sign.code] = {
        name: translated.name,
        category: sign.category,
        description: translated.description,
        svg: sign.svg
    };
    const filename = sign.assets.svg.split('/').pop();
    notice.push(`${sign.code} · https://commons.wikimedia.org/wiki/File:${encodeURIComponent(filename)}`);
}

const bundle = `/* Generado por scripts/build_floor_plan_signs.mjs. No editar manualmente.\n` +
    ` * @iso-safety-signs/core ${version} · MIT; pictogramas: consultar NOTICE.\n` +
    ` * 332 señales ISO 7010 locales, sin dependencias ni solicitudes externas.\n */\n` +
    `window.FLOOR_PLAN_SIGN_CATEGORIES = Object.freeze(${JSON.stringify(catalog.categories)});\n` +
    `window.FLOOR_PLAN_SIGNS = Object.freeze(${JSON.stringify(output)});\n`;
const outputPath = path.join(root, 'public/assets/js/floor-plan-signs.js');
fs.writeFileSync(outputPath, bundle);
fs.writeFileSync(path.join(root, 'public/assets/js/vendor/iso-safety-signs.NOTICE.txt'), notice.join('\n') + '\n');
console.log(`Biblioteca ISO 7010 generada: ${signs.length} señales · ${Buffer.byteLength(bundle)} bytes · sin contenido activo.`);
