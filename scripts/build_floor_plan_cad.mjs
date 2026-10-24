import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const categories = {
  furniture: { label: 'Mobiliario y estanterías', short: 'Mobiliario' },
  sanitary: { label: 'Baños', short: 'Baños' },
  kitchen: { label: 'Cocinas', short: 'Cocinas' },
  industrial: { label: 'Equipos industriales', short: 'Industrial' },
  vehicles: { label: 'Vehículos y maquinaria', short: 'Vehículos' },
  vertical: { label: 'Conexiones entre pisos', short: 'Entre pisos' },
};
const rect = (x, y, width, height, rx = 0, fill = false) => ({ type: 'rect', x, y, width, height, rx, fill });
const circle = (cx, cy, r, fill = false) => ({ type: 'circle', cx, cy, r, fill });
const ellipse = (cx, cy, rx, ry, fill = false) => ({ type: 'ellipse', cx, cy, rx, ry, fill });
const line = (x1, y1, x2, y2, dash = null) => ({ type: 'line', x1, y1, x2, y2, dash });
const pathShape = (d, fill = false) => ({ type: 'path', d, fill });
const polyline = (points) => ({ type: 'polyline', points });
const polygon = (points, fill = false) => ({ type: 'polygon', points, fill });
const object = (id, category, name, description, shapes, color = '#334155') => ({ id, category, name, description, shapes, color });
const objects = [
  object('table', 'furniture', 'Mesa de trabajo', 'Mesa rectangular para puestos, reuniones o áreas comunes.', [rect(15, 30, 70, 38, 2, true), line(20, 68, 20, 85), line(80, 68, 80, 85), line(25, 45, 75, 45, '5 4')]),
  object('desk', 'furniture', 'Escritorio en L', 'Puesto de trabajo con retorno lateral.', [pathShape('M15 20H82V43H42V82H15Z', true), line(28, 43, 28, 82), line(42, 55, 70, 55), circle(62, 31, 4)]),
  object('chair', 'furniture', 'Silla', 'Silla individual representada en planta.', [rect(30, 34, 40, 36, 6, true), pathShape('M26 34V20Q50 12 74 20V34'), line(34, 70, 30, 86), line(66, 70, 70, 86), line(38, 45, 62, 45)]),
  object('bench', 'furniture', 'Banca', 'Banca lineal para salas de espera o circulaciones.', [rect(10, 34, 80, 28, 3, true), line(35, 34, 35, 62), line(65, 34, 65, 62), line(18, 62, 18, 82), line(82, 62, 82, 82)]),
  object('shelf', 'furniture', 'Estantería', 'Estantería de almacenamiento con niveles visibles.', [rect(10, 12, 80, 76, 1, true), line(10, 30, 90, 30), line(10, 49, 90, 49), line(10, 68, 90, 68), line(24, 12, 24, 88), line(76, 12, 76, 88)]),
  object('locker', 'furniture', 'Lockers o casilleros', 'Batería de casilleros para personal o visitantes.', [rect(14, 8, 72, 84, 1, true), line(38, 8, 38, 92), line(62, 8, 62, 92), line(20, 20, 32, 20), line(44, 20, 56, 20), line(68, 20, 80, 20), circle(32, 52, 1.8), circle(56, 52, 1.8), circle(80, 52, 1.8)]),

  object('toilet', 'sanitary', 'Sanitario', 'Inodoro con tanque y taza en vista de planta.', [rect(32, 8, 36, 20, 2, true), ellipse(50, 58, 24, 31, true), ellipse(50, 58, 14, 21), line(34, 34, 66, 34), circle(50, 18, 2)]),
  object('washbasin', 'sanitary', 'Lavamanos', 'Lavamanos con cuenca, desagüe y grifería.', [ellipse(50, 54, 34, 29, true), ellipse(50, 54, 21, 16), circle(50, 56, 3), circle(50, 17, 5), line(50, 22, 50, 32), line(34, 18, 42, 18), line(58, 18, 66, 18)]),
  object('urinal', 'sanitary', 'Urinario', 'Urinario individual en representación de planta.', [pathShape('M25 14H75V58Q73 84 50 89Q27 84 25 58Z', true), pathShape('M35 30V57Q37 72 50 76Q63 72 65 57V30'), circle(50, 20, 3), line(40, 14, 40, 6), line(60, 14, 60, 6)]),
  object('shower', 'sanitary', 'Ducha', 'Zona de ducha con desagüe y punto de suministro.', [rect(12, 12, 76, 76, 1, true), line(12, 12, 88, 88, '5 4'), circle(50, 50, 5), circle(26, 26, 7), line(31, 31, 38, 38), line(22, 37, 18, 44), line(30, 39, 28, 47)]),

  object('kitchen_sink', 'kitchen', 'Lavaplatos doble', 'Lavaplatos de dos cubetas con grifería.', [rect(10, 25, 80, 52, 2, true), rect(16, 33, 31, 35, 4), rect(53, 33, 31, 35, 4), circle(31, 51, 3), circle(69, 51, 3), pathShape('M42 25V15Q50 5 58 15V25'), circle(50, 15, 3)]),
  object('stove', 'kitchen', 'Estufa de cuatro puestos', 'Estufa con cuatro quemadores y controles.', [rect(15, 12, 70, 76, 2, true), circle(35, 34, 12), circle(65, 34, 12), circle(35, 61, 12), circle(65, 61, 12), circle(35, 34, 7), circle(65, 34, 7), circle(35, 61, 7), circle(65, 61, 7), circle(30, 81, 2), circle(43, 81, 2), circle(57, 81, 2), circle(70, 81, 2)]),
  object('refrigerator', 'kitchen', 'Refrigerador', 'Refrigerador de dos compartimentos en planta.', [rect(25, 7, 50, 86, 2, true), line(25, 42, 75, 42), line(35, 18, 35, 33), line(35, 53, 35, 75), line(65, 18, 65, 33), line(65, 53, 65, 75)]),
  object('counter', 'kitchen', 'Mesón en L', 'Mesón de preparación con retorno y módulos.', [pathShape('M10 18H88V42H42V86H10Z', true), pathShape('M20 27H78V33H32V76H20Z'), line(42, 57, 70, 57), line(25, 42, 25, 86)]),

  object('drill_press', 'industrial', 'Taladro de banco', 'Taladro vertical con columna, mesa y base.', [rect(20, 82, 60, 10, 2, true), rect(40, 14, 45, 18, 3, true), rect(25, 54, 45, 8, 1, true), line(65, 32, 65, 82), line(50, 32, 50, 54), polygon([46, 54, 54, 54, 50, 62]), circle(78, 23, 5), line(78, 28, 78, 38)]),
  object('lathe', 'industrial', 'Torno', 'Torno paralelo con cabezal, carro y contrapunto.', [rect(10, 72, 80, 14, 2, true), rect(12, 34, 23, 32, 2, true), rect(67, 45, 19, 21, 2, true), rect(41, 55, 21, 13, 1, true), line(35, 50, 67, 50), circle(39, 50, 8), circle(64, 50, 6), line(18, 86, 18, 94), line(82, 86, 82, 94)]),
  object('welding', 'industrial', 'Equipo de soldadura', 'Fuente de soldadura con controles y cable de salida.', [rect(18, 18, 48, 58, 3, true), circle(30, 31, 4), circle(46, 31, 4), line(26, 45, 58, 45), line(26, 52, 58, 52), line(26, 59, 58, 59), circle(24, 80, 5), circle(60, 80, 5), pathShape('M66 61C90 61 92 88 66 88H48'), polyline('48,88 40,82 34,88 42,94')]),
  object('compressor', 'industrial', 'Compresor de aire', 'Compresor con tanque, motor, manómetro y ruedas.', [rect(15, 42, 68, 30, 15, true), rect(25, 20, 31, 21, 2, true), circle(68, 27, 7), line(68, 34, 68, 42), circle(26, 77, 9), circle(72, 77, 9), pathShape('M82 47L91 24H96'), line(34, 20, 34, 12), line(46, 20, 46, 12)]),
  object('circular_saw', 'industrial', 'Sierra circular de banco', 'Mesa de corte con disco y guía longitudinal.', [rect(10, 40, 80, 36, 2, true), circle(50, 45, 23), circle(50, 45, 3), line(50, 22, 50, 68), line(27, 45, 73, 45), line(34, 29, 66, 61), line(34, 61, 66, 29), line(20, 40, 20, 76), line(20, 76, 20, 92), line(80, 76, 80, 92), line(15, 55, 85, 55, '5 4')]),

  object('car', 'vehicles', 'Automóvil', 'Vehículo liviano en vista superior.', [pathShape('M31 7H69Q82 19 85 50Q82 81 69 93H31Q18 81 15 50Q18 19 31 7Z', true), pathShape('M28 27H72L78 41H22Z'), pathShape('M23 62H77L71 78H29Z'), line(22, 41, 22, 62), line(78, 41, 78, 62), rect(10, 25, 7, 18, 2), rect(83, 25, 7, 18, 2), rect(10, 60, 7, 18, 2), rect(83, 60, 7, 18, 2)]),
  object('truck', 'vehicles', 'Camión de carga', 'Camión con cabina y área de carga en planta.', [rect(34, 5, 32, 26, 4, true), rect(14, 31, 72, 61, 2, true), line(37, 20, 63, 20), line(14, 40, 86, 40), line(50, 40, 50, 92, '5 4'), rect(24, 11, 7, 15, 2), rect(69, 11, 7, 15, 2), rect(8, 48, 7, 18, 2), rect(85, 48, 7, 18, 2), rect(8, 72, 7, 16, 2), rect(85, 72, 7, 16, 2)]),
  object('forklift', 'vehicles', 'Montacargas', 'Equipo de elevación con mástil, horquillas y ruedas.', [rect(18, 36, 48, 34, 3, true), rect(25, 15, 34, 22, 2), line(25, 15, 18, 36), line(59, 15, 66, 36), circle(30, 75, 11), circle(60, 75, 11), line(74, 10, 74, 86), line(83, 10, 83, 86), line(83, 74, 98, 74), line(83, 85, 98, 85), line(68, 24, 88, 24)], '#ca8a04'),
  object('excavator', 'vehicles', 'Excavadora', 'Maquinaria con orugas, cabina, brazo y cucharón.', [rect(14, 72, 58, 17, 8, true), circle(26, 80, 5), circle(42, 80, 5), circle(59, 80, 5), rect(24, 47, 38, 25, 3, true), rect(43, 29, 21, 21, 2, true), polyline('55,34 67,14 84,29 91,55'), pathShape('M84 54L97 61L89 73L79 65Z', true), line(67, 14, 73, 10)], '#ca8a04'),
  object('backhoe', 'vehicles', 'Retroexcavadora', 'Equipo con cucharones frontal y posterior.', [circle(29, 74, 12), circle(70, 74, 15), rect(25, 45, 46, 23, 3, true), rect(41, 25, 22, 23, 2, true), polyline('26,51 12,35 6,50'), pathShape('M5 45L14 48L12 63L2 60Z', true), polyline('69,48 84,24 92,48'), pathShape('M88 43L97 48L92 62L83 56Z', true)], '#ca8a04'),
  object('crane', 'vehicles', 'Grúa móvil', 'Grúa con pluma, cable y gancho en representación lateral.', [rect(15, 55, 57, 21, 3, true), rect(20, 35, 23, 21, 2, true), circle(26, 80, 9), circle(61, 80, 9), line(40, 39, 87, 8), line(46, 44, 92, 14), line(87, 8, 87, 36), pathShape('M87 36Q87 46 81 42'), line(44, 55, 52, 35)], '#ca8a04'),
  object('bulldozer', 'vehicles', 'Bulldozer', 'Tractor de orugas con hoja de empuje frontal.', [rect(21, 62, 59, 24, 12, true), circle(33, 74, 7), circle(50, 74, 7), circle(68, 74, 7), rect(30, 36, 40, 29, 3, true), rect(42, 19, 24, 20, 2, true), pathShape('M10 43L21 49V85L8 79Z', true), line(20, 55, 36, 65), line(20, 75, 35, 72)], '#ca8a04'),

  object('stairs_straight', 'vertical', 'Escalera recta', 'Tramo recto con huellas y sentido de ascenso.', [rect(20, 7, 60, 86), ...[17, 27, 37, 47, 57, 67, 77, 87].map(y => line(20, y, 80, y)), line(50, 77, 50, 24), polygon([44, 30, 50, 18, 56, 30])]),
  object('stairs_l', 'vertical', 'Escalera en L', 'Dos tramos con cambio de dirección y descanso.', [pathShape('M14 8H46V53H90V91H14Z'), line(24, 8, 24, 53), line(35, 8, 35, 53), line(46, 62, 90, 62), line(46, 72, 90, 72), line(46, 82, 90, 82), rect(14, 53, 32, 38), polyline('30,43 30,25 30,43 70,43'), polygon([64, 37, 78, 43, 64, 49])]),
  object('stairs_spiral', 'vertical', 'Escalera de caracol', 'Escalera circular con huellas radiales.', [circle(50, 50, 43), circle(50, 50, 6), line(50, 44, 50, 7), line(54, 46, 80, 20), line(56, 50, 93, 50), line(54, 54, 80, 80), line(50, 56, 50, 93), line(46, 54, 20, 80), line(44, 50, 7, 50), line(46, 46, 20, 20), polygon([47, 15, 50, 7, 53, 15])]),
  object('elevator', 'vertical', 'Ascensor', 'Caja de ascensor con cabina y sentidos de desplazamiento.', [rect(14, 8, 72, 84, 1, true), rect(24, 18, 52, 64), line(50, 18, 50, 82), line(36, 65, 36, 38), polygon([30, 44, 36, 32, 42, 44]), line(64, 35, 64, 62), polygon([58, 56, 64, 68, 70, 56]), line(14, 8, 24, 18), line(86, 8, 76, 18)]),
  object('escalator', 'vertical', 'Escalera eléctrica', 'Tramo inclinado con peldaños y flecha de ascenso.', [rect(14, 8, 72, 84), line(25, 77, 75, 27), line(31, 83, 81, 33), line(30, 67, 38, 75), line(39, 56, 47, 64), line(48, 45, 56, 53), line(57, 34, 65, 42), line(34, 70, 69, 35), polygon([60, 35, 72, 32, 69, 44])]),
  object('ramp', 'vertical', 'Rampa entre niveles', 'Rampa con pendiente y sentido de ascenso.', [rect(12, 24, 76, 52, 1, true), line(20, 24, 20, 76), line(80, 24, 80, 76), line(25, 65, 72, 36), polygon([61, 36, 75, 33, 70, 46]), line(25, 70, 75, 70, '5 4')]),
];

function number(value, key) {
  if (typeof value !== 'number' || !Number.isFinite(value)) throw new Error(`Coordenada inválida: ${key}`);
  return value;
}
function renderShape(shape) {
  const fill = shape.fill ? ' fill="currentColor" fill-opacity=".09"' : ' fill="none"';
  const dash = shape.dash ? ` stroke-dasharray="${shape.dash}"` : '';
  if (shape.type === 'rect') return `<rect x="${number(shape.x, 'x')}" y="${number(shape.y, 'y')}" width="${number(shape.width, 'width')}" height="${number(shape.height, 'height')}" rx="${number(shape.rx, 'rx')}"${fill}${dash}/>`;
  if (shape.type === 'circle') return `<circle cx="${number(shape.cx, 'cx')}" cy="${number(shape.cy, 'cy')}" r="${number(shape.r, 'r')}"${fill}${dash}/>`;
  if (shape.type === 'ellipse') return `<ellipse cx="${number(shape.cx, 'cx')}" cy="${number(shape.cy, 'cy')}" rx="${number(shape.rx, 'rx')}" ry="${number(shape.ry, 'ry')}"${fill}${dash}/>`;
  if (shape.type === 'line') return `<line x1="${number(shape.x1, 'x1')}" y1="${number(shape.y1, 'y1')}" x2="${number(shape.x2, 'x2')}" y2="${number(shape.y2, 'y2')}"${fill}${dash}/>`;
  if (shape.type === 'path') return `<path d="${shape.d}"${fill}${dash}/>`;
  if (shape.type === 'polyline') return `<polyline points="${shape.points}"${fill}${dash}/>`;
  if (shape.type === 'polygon') return `<polygon points="${shape.points}"${fill}${dash}/>`;
  throw new Error(`Forma no soportada: ${shape.type}`);
}
if (objects.length !== 32 || new Set(objects.map(item => item.id)).size !== objects.length) throw new Error('La biblioteca CAD debe contener 32 IDs únicos.');
const catalog = { version: 1, categories, objects: {} };
for (const item of objects) {
  if (!categories[item.category] || !/^[a-z][a-z0-9_]{2,30}$/.test(item.id) || !item.name || !item.description || !Array.isArray(item.shapes)) {
    throw new Error(`Metadatos CAD inválidos: ${item.id}`);
  }
  if (!['#334155', '#ca8a04'].includes(item.color)) throw new Error(`Color CAD no permitido: ${item.id}`);
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${item.shapes.map(renderShape).join('')}</svg>`;
  if (/script|onload|href=|foreignObject/i.test(svg)) throw new Error(`SVG CAD inseguro: ${item.id}`);
  catalog.objects[`cad_${item.id}`] = {
    category: item.category,
    name: item.name,
    description: `${item.description} Representación CAD esquemática; verifica dimensiones y escala en sitio.`,
    reference: 'CAD',
    color: item.color,
    svg,
  };
}
const json = JSON.stringify(catalog, null, 2) + '\n';
const bundle = '/* Biblioteca CAD local: geometría esquemática propia, sin recursos externos. */\nwindow.FLOOR_PLAN_CAD = ' + JSON.stringify({ categories, objects: catalog.objects }) + ';\n';
fs.writeFileSync(path.join(root, 'resources/cad-objects.json'), json);
fs.writeFileSync(path.join(root, 'public/assets/js/floor-plan-cad.js'), bundle);
console.log(`Biblioteca CAD generada: ${objects.length} objetos · ${Buffer.byteLength(bundle)} bytes · seis familias.`);
