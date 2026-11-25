<?php
/**
 * Generador de Acta Oficial de Asistencia FISEI en PDF (FPDF)
 */

date_default_timezone_set('America/Guayaquil');

require_once __DIR__ . '/../controllers/controller.php';
require_once __DIR__ . '/../models/reporteModel.php';
require_once __DIR__ . '/../libs/fpdf/fpdf.php';

controllersMvc::iniciarSesion();

if (!controllersMvc::sesionActiva()) {
    header('Location: ../index.php?action=login');
    exit;
}

$idUsuario  = (int)$_SESSION['id_usuario'];
$rolUsuario = $_SESSION['rol'];
$idMateria  = isset($_GET['id_materia']) ? (int)$_GET['id_materia'] : 0;
$parcial    = isset($_GET['parcial']) ? $_GET['parcial'] : 'Parcial 1';

try {
    $datos = ReporteModel::obtenerConsolidado($idMateria, $parcial, $idUsuario, $rolUsuario);
} catch (Exception $e) {
    die("Error al generar PDF: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

// Convierte tildes y ñ de UTF-8 al formato que acepta FPDF
function textoPdf($texto) {
    return mb_convert_encoding((string)$texto, 'ISO-8859-1', 'UTF-8');
}

// Recorta textos muy largos para que no se salgan de la celda
function recortar($texto, $max) {
    if (mb_strlen($texto, 'UTF-8') > $max) {
        return mb_substr($texto, 0, $max - 1, 'UTF-8') . '.';
    }
    return $texto;
}

class ActaFiseiPDF extends FPDF {
    public function Header() {
        // Banda superior institucional FISEI - UTA (#8B0000)
        $this->SetFillColor(139, 0, 0);
        $this->Rect(10, 10, 190, 20, 'F');

        $this->SetY(12);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 13);
        $this->Cell(190, 7, textoPdf('UNIVERSIDAD TÉCNICA DE AMBATO'), 0, 1, 'C');

        $this->SetFont('Arial', '', 9.5);
        $this->Cell(190, 6, textoPdf('FACULTAD DE INGENIERÍA EN SISTEMAS, ELECTRÓNICA E INDUSTRIAL (FISEI)'), 0, 1, 'C');

        // Título del documento debajo del membrete
        $this->Ln(5);
        $this->SetTextColor(139, 0, 0);
        $this->SetFont('Arial', 'B', 11.5);
        $this->Cell(190, 7, textoPdf('ACTA OFICIAL DE CONTROL DE ASISTENCIA'), 0, 1, 'C');

        // Línea separadora
        $this->SetDrawColor(139, 0, 0);
        $this->SetLineWidth(0.5);
        $this->Line(10, $this->GetY() + 1, 200, $this->GetY() + 1);
        $this->SetLineWidth(0.2);

        $this->SetTextColor(30, 41, 59);
        $this->Ln(4);
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetDrawColor(200, 200, 200);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(2);

        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(135, 5, textoPdf('Sistema de Control de Asistencia FISEI - UTA | Ambato, Ecuador'), 0, 0, 'L');
        $this->Cell(55, 5, textoPdf('Página ' . $this->PageNo() . ' de {nb}'), 0, 0, 'R');
    }

    public function tituloSeccion($texto) {
        $this->SetFont('Arial', 'B', 9.5);
        $this->SetTextColor(139, 0, 0);
        $this->Cell(190, 6, textoPdf($texto), 0, 1, 'L');
        $this->SetTextColor(30, 41, 59);
    }
}

$materia     = $datos['materia'];
$resumen     = $datos['resumen'];
$estudiantes = $datos['estudiantes'];
$fechas      = $datos['fechas'];

$pdf = new ActaFiseiPDF('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 18);
$pdf->AddPage();

// ==============================================================================
// SECCIÓN 1: DATOS GENERALES DE LA ASIGNATURA
// ==============================================================================
$pdf->tituloSeccion('1. DATOS GENERALES');

$pdf->SetFillColor(248, 250, 252);
$pdf->SetDrawColor(203, 213, 225);

// Fila 1
$pdf->SetFont('Arial', 'B', 8.5);
$pdf->Cell(28, 6.5, textoPdf('Asignatura:'), 'LT', 0, 'L', true);
$pdf->SetFont('Arial', '', 8.5);
$pdf->Cell(97, 6.5, textoPdf(recortar($materia['codigo'] . ' - ' . $materia['nombre'], 52)), 'T', 0, 'L', true);
$pdf->SetFont('Arial', 'B', 8.5);
$pdf->Cell(25, 6.5, textoPdf('Periodo:'), 'T', 0, 'L', true);
$pdf->SetFont('Arial', '', 8.5);
$pdf->Cell(40, 6.5, textoPdf($materia['periodo']), 'RT', 1, 'L', true);

// Fila 2
$pdf->SetFont('Arial', 'B', 8.5);
$pdf->Cell(28, 6.5, textoPdf('Docente:'), 'L', 0, 'L', true);
$pdf->SetFont('Arial', '', 8.5);
$pdf->Cell(97, 6.5, textoPdf(recortar($materia['docente'], 52)), 0, 0, 'L', true);
$pdf->SetFont('Arial', 'B', 8.5);
$pdf->Cell(25, 6.5, textoPdf('Parcial:'), 0, 0, 'L', true);
$pdf->SetFont('Arial', '', 8.5);
$pdf->Cell(40, 6.5, textoPdf($datos['parcial']), 'R', 1, 'L', true);

// Fila 3
$pdf->SetFont('Arial', 'B', 8.5);
$pdf->Cell(28, 6.5, textoPdf('Total Clases:'), 'LB', 0, 'L', true);
$pdf->SetFont('Arial', '', 8.5);
$pdf->Cell(97, 6.5, textoPdf($resumen['total_clases'] . ' sesión(es) impartida(s)'), 'B', 0, 'L', true);
$pdf->SetFont('Arial', 'B', 8.5);
$pdf->Cell(25, 6.5, textoPdf('Emisión:'), 'B', 0, 'L', true);
$pdf->SetFont('Arial', '', 8.5);
$pdf->Cell(40, 6.5, date('d/m/Y H:i'), 'RB', 1, 'L', true);

$pdf->Ln(4);

// ==============================================================================
// SECCIÓN 2: RESUMEN GENERAL DEL CURSO
// ==============================================================================
$pdf->tituloSeccion('2. RESUMEN GENERAL');

$pdf->SetDrawColor(203, 213, 225);

// Fila de etiquetas
$pdf->SetFont('Arial', 'B', 8.5);
$pdf->SetFillColor(241, 245, 249);
$pdf->SetTextColor(51, 65, 85);
$pdf->Cell(47.5, 6.5, textoPdf('Total Estudiantes'), 'LTR', 0, 'C', true);

$pdf->SetFillColor(220, 252, 231);
$pdf->SetTextColor(22, 101, 52);
$pdf->Cell(47.5, 6.5, textoPdf('Aprobados'), 'LTR', 0, 'C', true);

$pdf->SetFillColor(254, 249, 195);
$pdf->SetTextColor(133, 77, 14);
$pdf->Cell(47.5, 6.5, textoPdf('En Riesgo'), 'LTR', 0, 'C', true);

$pdf->SetFillColor(254, 226, 226);
$pdf->SetTextColor(153, 27, 27);
$pdf->Cell(47.5, 6.5, textoPdf('Reprobados'), 'LTR', 1, 'C', true);

// Fila de valores numéricos
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetFillColor(255, 255, 255);
$pdf->SetTextColor(30, 41, 59);
$pdf->Cell(47.5, 8, $resumen['total_estudiantes'], 'LBR', 0, 'C', true);

$pdf->SetTextColor(22, 101, 52);
$pdf->Cell(47.5, 8, $resumen['aprobados'], 'LBR', 0, 'C', true);

$pdf->SetTextColor(161, 98, 7);
$pdf->Cell(47.5, 8, $resumen['en_riesgo'], 'LBR', 0, 'C', true);

$pdf->SetTextColor(185, 28, 28);
$pdf->Cell(47.5, 8, $resumen['reprobados'], 'LBR', 1, 'C', true);

$pdf->SetTextColor(30, 41, 59);
$pdf->Ln(5);

// ==============================================================================
// SECCIÓN 3: CONSOLIDADO DE ASISTENCIA POR ESTUDIANTE
// ==============================================================================
$pdf->tituloSeccion('3. CONSOLIDADO DE ASISTENCIA POR ESTUDIANTE');

// Encabezado de tabla (suma exacta: 190mm)
$pdf->SetFillColor(139, 0, 0);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetDrawColor(139, 0, 0);
$pdf->SetFont('Arial', 'B', 8);

$pdf->Cell(8, 7.5, textoPdf('N°'), 1, 0, 'C', true);
$pdf->Cell(21, 7.5, textoPdf('Cédula'), 1, 0, 'C', true);
$pdf->Cell(57, 7.5, textoPdf('Apellidos y Nombres'), 1, 0, 'L', true);
$pdf->Cell(12, 7.5, textoPdf('Pres.'), 1, 0, 'C', true);
$pdf->Cell(12, 7.5, textoPdf('Atras.'), 1, 0, 'C', true);
$pdf->Cell(12, 7.5, textoPdf('Faltas'), 1, 0, 'C', true);
$pdf->Cell(12, 7.5, textoPdf('Justif.'), 1, 0, 'C', true);
$pdf->Cell(15, 7.5, textoPdf('Válidas'), 1, 0, 'C', true);
$pdf->Cell(15, 7.5, textoPdf('% Asist.'), 1, 0, 'C', true);
$pdf->Cell(26, 7.5, textoPdf('Estado'), 1, 1, 'C', true);

$pdf->SetDrawColor(203, 213, 225);
$pdf->SetFont('Arial', '', 8);

if (count($estudiantes) === 0) {
    $pdf->SetTextColor(100, 116, 139);
    $pdf->Cell(190, 9, textoPdf('No existen estudiantes matriculados en esta asignatura.'), 1, 1, 'C');
} else {
    $contador = 1;
    foreach ($estudiantes as $est) {
        // Alternar color de fila para mejor lectura
        if ($contador % 2 === 0) {
            $pdf->SetFillColor(248, 250, 252);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }

        $nombreCompleto = recortar($est['apellidos'] . ' ' . $est['nombres'], 33);

        $pdf->SetTextColor(30, 41, 59);
        $pdf->Cell(8, 7, $contador, 1, 0, 'C', true);
        $pdf->Cell(21, 7, textoPdf($est['cedula']), 1, 0, 'C', true);
        $pdf->Cell(57, 7, textoPdf($nombreCompleto), 1, 0, 'L', true);
        $pdf->Cell(12, 7, $est['presentes'], 1, 0, 'C', true);
        $pdf->Cell(12, 7, $est['atrasos'], 1, 0, 'C', true);
        $pdf->Cell(12, 7, $est['faltas'], 1, 0, 'C', true);
        $pdf->Cell(12, 7, $est['justificados'], 1, 0, 'C', true);
        $pdf->Cell(15, 7, $est['asistencias_validas'] . ' / ' . $est['total_clases'], 1, 0, 'C', true);

        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(15, 7, number_format($est['porcentaje'], 1) . '%', 1, 0, 'C', true);

        // Color de la celda Estado
        $estado = $est['estado_academico'];
        if ($estado === 'APROBADO') {
            $pdf->SetFillColor(220, 252, 231);
            $pdf->SetTextColor(22, 101, 52);
        } elseif ($estado === 'EN RIESGO') {
            $pdf->SetFillColor(254, 249, 195);
            $pdf->SetTextColor(133, 77, 14);
        } elseif ($estado === 'REPROBADO') {
            $pdf->SetFillColor(254, 226, 226);
            $pdf->SetTextColor(153, 27, 27);
        } else {
            $pdf->SetFillColor(241, 245, 249);
            $pdf->SetTextColor(71, 85, 105);
        }

        $pdf->Cell(26, 7, textoPdf($estado), 1, 1, 'C', true);
        $pdf->SetFont('Arial', '', 8);
        $contador++;
    }
}

// ==============================================================================
// SECCIÓN 4: DESGLOSE POR FECHAS DE CLASE
// ==============================================================================
function dibujarTablaFechas($pdf, $estudiantes, $listaFechas) {
    // Mostrar hasta 10 fechas por tabla para no salirse del ancho de la hoja
    $bloques = array_chunk($listaFechas, 10);

    foreach ($bloques as $bloque) {
        $numFechas = count($bloque);
        $anchoFecha = 13;
        $anchoAlumno = 190 - ($numFechas * $anchoFecha);

        // Encabezado del bloque de fechas
        $pdf->SetFillColor(30, 41, 59);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetDrawColor(30, 41, 59);
        $pdf->SetFont('Arial', 'B', 7.5);
        $pdf->Cell($anchoAlumno, 6.5, textoPdf('Estudiante'), 1, 0, 'L', true);

        foreach ($bloque as $f) {
            $partes = explode('-', $f);
            $fechaCorta = $partes[2] . '/' . $partes[1];
            $pdf->Cell($anchoFecha, 6.5, $fechaCorta, 1, 0, 'C', true);
        }
        $pdf->Ln();

        $pdf->SetDrawColor(203, 213, 225);
        $pdf->SetFont('Arial', '', 7.5);

        $fila = 1;
        foreach ($estudiantes as $est) {
            if ($fila % 2 === 0) {
                $pdf->SetFillColor(248, 250, 252);
            } else {
                $pdf->SetFillColor(255, 255, 255);
            }

            $pdf->SetTextColor(30, 41, 59);
            $pdf->Cell($anchoAlumno, 6.5, textoPdf(recortar($est['apellidos'] . ' ' . $est['nombres'], 32)), 1, 0, 'L', true);

            foreach ($bloque as $f) {
                $estado = $est['detalle_fechas'][$f];
                $letra = '-';
                $pdf->SetTextColor(148, 163, 184);

                if ($estado === 'PRESENTE') {
                    $letra = 'P';
                    $pdf->SetTextColor(22, 101, 52);
                } elseif ($estado === 'ATRASO') {
                    $letra = 'A';
                    $pdf->SetTextColor(161, 98, 7);
                } elseif ($estado === 'FALTA') {
                    $letra = 'F';
                    $pdf->SetTextColor(185, 28, 28);
                } elseif ($estado === 'JUSTIFICADO') {
                    $letra = 'J';
                    $pdf->SetTextColor(29, 78, 216);
                }

                $pdf->SetFont('Arial', 'B', 7.5);
                $pdf->Cell($anchoFecha, 6.5, $letra, 1, 0, 'C', true);
            }
            $pdf->SetFont('Arial', '', 7.5);
            $pdf->Ln();
            $fila++;
        }

        $pdf->Ln(3);
    }
}

if (count($fechas) > 0 && count($estudiantes) > 0) {
    $pdf->Ln(6);
    $pdf->tituloSeccion('4. DESGLOSE DE ASISTENCIA POR FECHA DE CLASE');
    $pdf->Ln(1);

    if ($datos['parcial'] === 'Reporte Semestre') {
        $fechasParcial = isset($datos['fechas_parcial']) ? $datos['fechas_parcial'] : [];
        $fechasP1 = [];
        $fechasP2 = [];

        foreach ($fechas as $f) {
            if (isset($fechasParcial[$f]) && $fechasParcial[$f] === 'Parcial 2') {
                $fechasP2[] = $f;
            } else {
                $fechasP1[] = $f;
            }
        }

        if (count($fechasP1) > 0) {
            $pdf->SetFillColor(241, 245, 249);
            $pdf->SetDrawColor(203, 213, 225);
            $pdf->SetTextColor(30, 41, 59);
            $pdf->SetFont('Arial', 'B', 8.5);
            $pdf->Cell(190, 6, textoPdf('Sesiones - Parcial 1'), 1, 1, 'L', true);
            dibujarTablaFechas($pdf, $estudiantes, $fechasP1);
        }

        if (count($fechasP2) > 0) {
            $pdf->SetFillColor(241, 245, 249);
            $pdf->SetDrawColor(203, 213, 225);
            $pdf->SetTextColor(30, 41, 59);
            $pdf->SetFont('Arial', 'B', 8.5);
            $pdf->Cell(190, 6, textoPdf('Sesiones - Parcial 2'), 1, 1, 'L', true);
            dibujarTablaFechas($pdf, $estudiantes, $fechasP2);
        }
    } else {
        dibujarTablaFechas($pdf, $estudiantes, $fechas);
    }
}

// ==============================================================================
// SECCIÓN 5: FIRMAS DE RESPONSABILIDAD
// ==============================================================================
if ($pdf->GetY() > 240) {
    $pdf->AddPage();
} else {
    $pdf->Ln(16);
}

$yFirma = $pdf->GetY();
$pdf->SetDrawColor(71, 85, 105);
$pdf->Line(22, $yFirma, 88, $yFirma);
$pdf->Line(122, $yFirma, 188, $yFirma);

$pdf->SetTextColor(30, 41, 59);
$pdf->SetFont('Arial', 'B', 8.5);
$pdf->SetXY(15, $yFirma + 2);
$pdf->Cell(80, 5, textoPdf($materia['docente']), 0, 0, 'C');
$pdf->SetXY(115, $yFirma + 2);
$pdf->Cell(80, 5, textoPdf('Coordinación / Dirección de Carrera'), 0, 1, 'C');

$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(100, 116, 139);
$pdf->SetX(15);
$pdf->Cell(80, 4.5, textoPdf('Docente Responsable'), 0, 0, 'C');
$pdf->SetX(115);
$pdf->Cell(80, 4.5, textoPdf('FISEI - Universidad Técnica de Ambato'), 0, 1, 'C');

$pdf->Output('I', 'Acta_Asistencia_' . $materia['codigo'] . '_' . str_replace(' ', '_', $datos['parcial']) . '.pdf');
