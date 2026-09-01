<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
use setasign\Fpdi\Fpdi;

/**
 * Gera a credencial em 2 páginas (FPDI + template em /public/templates/{idoso|pcd}.pdf)
 * - Página 1: imprime SOMENTE os valores de Nº do registro, Validade e Data de Expedição
 * - Página 2: imprime SOMENTE o Nome do Beneficiário
 * - Fonte: Arial 12 BOLD (em todos os campos)
 *
 * IMPORTANTE:
 * 1) Ajuste fino das coordenadas no array $POS abaixo se precisar alinhar 100% ao seu modelo.
 * 2) Garanta que os templates tenham DUAS páginas.
 * 3) Coloque os templates em: public/templates/idoso.pdf e public/templates/pcd.pdf
 */
function gerarPDF(
    string $tipo,             // 'IDOSO' | 'PCD'
    string $nome,             // Beneficiário (vai na Pág. 2)
    string $numeroFormatado,  // ex.: 001/2025 (vai na frente de "Nº DO REGISTRO:")
    string $dataInicioBR,     // dd/mm/aaaa (DATA DE EXPEDIÇÃO)
    string $dataFimBR,        // dd/mm/aaaa (VALIDADE)
    ?string $cid
): string
{
    // ======================== POSIÇÕES (em milímetros) ========================
    // A4 paisagem: 297 x 210 mm. Origem (0,0) no canto superior esquerdo.
    // Ajuste se necessário, mas estes valores já posicionam ao lado dos rótulos
    // nos templates que você enviou (o grosso fica correto; milimetria fina pode
    // ser calibrada depois mexendo nos números).
    $POS = [
           'page1' => [
        'numero'    => ['x' => 150, 'y' => 82, 'w' => 80, 'h' => 6, 'align' => 'L'],
        'validade'  => ['x' => 81, 'y' => 108, 'w' => 60, 'h' => 6, 'align' => 'L'],
        'expedicao' => ['x' => 215, 'y' => 108, 'w' => 60, 'h' => 6, 'align' => 'L'],
    ],
    'page2' => [
       'nome' => ['x' => 0, 'y' => 20, 'w' => 297, 'h' => 7, 'align' => 'C'],

        ],
    ];
    // ==========================================================================

    // Saída
    $outDir = __DIR__ . '/../data/generated';
    if (!is_dir($outDir)) {
        mkdir($outDir, 0777, true);
    }
    $safeNum  = str_replace('/', '-', $numeroFormatado);
    $fileName = sprintf('%s_%s.pdf', strtolower($tipo), $safeNum);
    $outPath  = (realpath($outDir) ?: $outDir) . DIRECTORY_SEPARATOR . $fileName;

    // Template (2 páginas)
    $template = __DIR__ . '/../public/templates/' . strtolower($tipo) . '.pdf';
    if (!is_file($template)) {
        throw new RuntimeException('Template não encontrado: ' . $template);
    }

    // Carrega template
    $pdf = new FPDI('L', 'mm', 'A4');
    $pdf->SetAutoPageBreak(false);
    $pageCount = $pdf->setSourceFile($template);
    if ($pageCount < 2) {
        throw new RuntimeException('O template precisa ter DUAS páginas: ' . basename($template));
    }

    // Configura fonte padrão: Arial 12 Bold
    $pdf->SetFont('Arial', 'B', 20);

    // ------------------- Página 1 -------------------
    $pdf->AddPage();
    $tpl1 = $pdf->importPage(1);
    $pdf->useTemplate($tpl1, 0, 0, 297);

    // Nº do registro
    $pdf->SetXY($POS['page1']['numero']['x'], $POS['page1']['numero']['y']);
    $pdf->Cell($POS['page1']['numero']['w'], $POS['page1']['numero']['h'], utf8_decode($numeroFormatado), 0, 0, $POS['page1']['numero']['align']);

    // Validade
    $pdf->SetXY($POS['page1']['validade']['x'], $POS['page1']['validade']['y']);
    $pdf->Cell($POS['page1']['validade']['w'], $POS['page1']['validade']['h'], $dataFimBR, 0, 0, $POS['page1']['validade']['align']);

    // Data de expedição
    $pdf->SetXY($POS['page1']['expedicao']['x'], $POS['page1']['expedicao']['y']);
    $pdf->Cell($POS['page1']['expedicao']['w'], $POS['page1']['expedicao']['h'], $dataInicioBR, 0, 0, $POS['page1']['expedicao']['align']);

    // ------------------- Página 2 -------------------
    $pdf->AddPage();
    $tpl2 = $pdf->importPage(2);
    $pdf->useTemplate($tpl2, 0, 0, 297);

    // Nome do beneficiário (somente isso)
    $pdf->SetXY($POS['page2']['nome']['x'], $POS['page2']['nome']['y']);
    // Limpa eventuais quebras exageradas usando Cell simples (uma linha)
    $nomeLinha = mb_strtoupper($nome, 'UTF-8');
    $pdf->Cell($POS['page2']['nome']['w'], $POS['page2']['nome']['h'], utf8_decode($nomeLinha), 0, 0, $POS['page2']['nome']['align']);

    // Salva
    $pdf->Output('F', $outPath);
    return $outPath;
}
