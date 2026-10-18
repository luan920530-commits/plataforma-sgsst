<?php
namespace App\Controllers;

use App\Auth;
use App\Database;

class MedevacController extends BaseController
{
    /**
     * GET /medevac
     */
    public function index(): void
    {
        $empresas = Database::query(
            'SELECT e.*,
                    (SELECT COUNT(*) FROM personal p
                     WHERE p.empresa_id = e.id AND p.activo = 1) AS total_personal
             FROM empresas e
             WHERE e.usuario_id = ?
             ORDER BY e.razon_social',
            [Auth::id()]
        );

        $this->view('medevac/index', [
            'titulo'   => 'MEDEVAC',
            'empresas' => $empresas,
        ]);
    }

    /**
     * GET /medevac/empresa/{id}
     */
    public function porEmpresa(string $id): void
    {
        $empresaId = (int) $id;

        $empresa = Database::first(
            'SELECT * FROM empresas WHERE id = ? AND usuario_id = ?',
            [$empresaId, Auth::id()]
        );

        if (!$empresa) {
            flash('error', 'Empresa no encontrada');
            $this->redirect(base_url('medevac'));
        }

        $personal = Database::query(
            'SELECT * FROM personal
             WHERE empresa_id = ? AND activo = 1
             ORDER BY nombre_completo',
            [$empresaId]
        );

        $this->view('medevac/empresa', [
            'titulo'   => 'MEDEVAC — ' . $empresa['razon_social'],
            'empresa'  => $empresa,
            'personal' => $personal,
        ]);
    }

    /**
     * GET /medevac/empresa/{id}/exportar
     * Descarga un .xlsx con la información MEDEVAC
     */
    public function exportar(string $id): void
    {
        $empresaId = (int) $id;

        $empresa = Database::first(
            'SELECT * FROM empresas WHERE id = ? AND usuario_id = ?',
            [$empresaId, Auth::id()]
        );

        if (!$empresa) {
            http_response_code(404);
            die('Empresa no encontrada');
        }

        $personal = Database::query(
            'SELECT * FROM personal WHERE empresa_id = ? AND activo = 1 ORDER BY nombre_completo',
            [$empresaId]
        );

        $x = new \App\XlsxBuilder();

        $filas = [];
        $filas[] = [['valor' => 'MEDEVAC — ' . $empresa['razon_social'], 'estilo' => \App\XlsxBuilder::S_TITLE]];
        $merges = ['A1:M1'];
        $filas[] = [['valor' => 'NIT: ' . $empresa['nit'] . ' · Fecha: ' . date('d/m/Y'), 'estilo' => \App\XlsxBuilder::S_SUBTITLE]];
        $merges[] = 'A2:M2';
        $filas[] = [];

        $filas[] = [
            ['valor' => 'N°',              'estilo' => \App\XlsxBuilder::S_HEADER],
            ['valor' => 'Nombre',          'estilo' => \App\XlsxBuilder::S_HEADER],
            ['valor' => 'Cédula',          'estilo' => \App\XlsxBuilder::S_HEADER],
            ['valor' => 'Cargo',           'estilo' => \App\XlsxBuilder::S_HEADER],
            ['valor' => 'RH',              'estilo' => \App\XlsxBuilder::S_HEADER],
            ['valor' => 'Celular',         'estilo' => \App\XlsxBuilder::S_HEADER],
            ['valor' => 'EPS',             'estilo' => \App\XlsxBuilder::S_HEADER],
            ['valor' => 'AFP',             'estilo' => \App\XlsxBuilder::S_HEADER],
            ['valor' => 'Contacto',        'estilo' => \App\XlsxBuilder::S_HEADER],
            ['valor' => 'Tel. contacto',   'estilo' => \App\XlsxBuilder::S_HEADER],
            ['valor' => 'Alergias',        'estilo' => \App\XlsxBuilder::S_HEADER],
            ['valor' => 'Brigada',         'estilo' => \App\XlsxBuilder::S_HEADER],
            ['valor' => 'Firma',           'estilo' => \App\XlsxBuilder::S_HEADER],
        ];

        foreach ($personal as $i => $p) {
            $filas[] = [
                ['valor' => $i + 1, 'estilo' => \App\XlsxBuilder::S_CENTER],
                ['valor' => $p['nombre_completo'], 'estilo' => \App\XlsxBuilder::S_BOLD],
                ['valor' => $p['cedula'] ?? '', 'estilo' => \App\XlsxBuilder::S_VALUE],
                ['valor' => $p['cargo'] ?? '', 'estilo' => \App\XlsxBuilder::S_VALUE],
                ['valor' => $p['rh'] ?? '', 'estilo' => \App\XlsxBuilder::S_CENTER],
                ['valor' => $p['celular'] ?? '', 'estilo' => \App\XlsxBuilder::S_VALUE],
                ['valor' => $p['eps'] ?? '', 'estilo' => \App\XlsxBuilder::S_VALUE],
                ['valor' => $p['afp'] ?? '', 'estilo' => \App\XlsxBuilder::S_VALUE],
                ['valor' => $p['contacto_emergencia_nombre'] ?? '', 'estilo' => \App\XlsxBuilder::S_VALUE],
                ['valor' => $p['contacto_emergencia_tel'] ?? '', 'estilo' => \App\XlsxBuilder::S_VALUE],
                ['valor' => $p['alergias'] ?? '', 'estilo' => \App\XlsxBuilder::S_WRAP],
                ['valor' => $p['entrenado_brigada'] ?? '—', 'estilo' => \App\XlsxBuilder::S_VALUE],
                ['valor' => !empty($p['firma_url']) ? 'Firmado' : 'Sin firma',
                 'estilo' => !empty($p['firma_url']) ? \App\XlsxBuilder::S_VERDE : \App\XlsxBuilder::S_CENTER],
            ];
        }

        $x->agregarHoja('MEDEVAC', $filas, [
            'anchos' => [
                'A' => 5, 'B' => 28, 'C' => 15, 'D' => 18, 'E' => 6, 'F' => 14,
                'G' => 18, 'H' => 18, 'I' => 22, 'J' => 16, 'K' => 25, 'L' => 22, 'M' => 12,
            ],
            'merges' => $merges,
            'freeze' => 'A5',
        ]);

        $nombre = 'MEDEVAC_' . $empresa['nit'] . '_' . date('Ymd_His') . '.xlsx';
        $ruta   = BASE_PATH . '/storage/documentos/' . $nombre;
        $x->guardar($ruta);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $nombre . '"');
        header('Content-Length: ' . filesize($ruta));
        readfile($ruta);
        @unlink($ruta);
        exit;
    }
}
