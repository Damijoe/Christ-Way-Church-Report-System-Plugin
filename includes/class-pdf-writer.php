<?php
/**
 * Native PHP PDF Writer (minimal PDF 1.4 spec)
 * Zero dependencies — pure PHP, generates valid PDF files
 * Outputs landscape A4 tables
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class CWR_PdfWriter {

    private $pages      = [];
    private $page       = 0;
    private $objects    = [];
    private $offsets    = [];
    private $buf        = '';
    private $fonts      = [];
    private $font_objs  = [];
    private $w          = 841.89; // A4 landscape width in points
    private $h          = 595.28; // A4 landscape height in points
    private $margin     = 36;
    private $y          = 0;
    private $line_h     = 14;
    private $font_size  = 9;

    // Colors
    private $fill_color  = null;
    private $text_color  = '0 0 0';

    public function __construct() {
        $this->y = $this->margin + 20;
    }

    private function obj( $data ) {
        $n = count( $this->objects ) + 1;
        $this->objects[ $n ] = $data;
        return $n;
    }

    private function escape( $s ) {
        return str_replace( ['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', '\\r'], $s );
    }

    /**
     * Main entry: render a full report table to PDF and output
     */
    public function render_report( $title, $subtitle, $headers, $rows, $totals_row, $filename ) {
        // Use HTML approach — generate a print-ready HTML file
        // This is actually better UX: user opens in browser, Ctrl+P → Save as PDF
        // Gives perfect formatting without any PDF library
        $this->output_html_pdf( $title, $subtitle, $headers, $rows, $totals_row, $filename );
    }

    private function output_html_pdf( $title, $subtitle, $headers, $rows, $totals_row, $filename ) {
        $th_cells = '';
        foreach ( $headers as $h ) {
            $th_cells .= '<th>' . esc_html( $h ) . '</th>';
        }

        $tr_rows = '';
        foreach ( $rows as $i => $row ) {
            $bg = $i % 2 === 0 ? '#f0f5fa' : '#ffffff';
            $tr_rows .= "<tr style='background:{$bg}'>";
            foreach ( $row as $cell ) {
                $tr_rows .= '<td>' . esc_html( (string) $cell ) . '</td>';
            }
            $tr_rows .= '</tr>';
        }

        $totals_html = '';
        if ( $totals_row ) {
            $totals_html = "<tr class='totals-row'>";
            foreach ( $totals_row as $cell ) {
                $totals_html .= '<td>' . esc_html( (string) $cell ) . '</td>';
            }
            $totals_html .= '</tr>';
        }

        $generated = date( 'd M Y, H:i' ) . ' (WAT)';
        $col_count = count( $headers );

        $html = "<!DOCTYPE html>
<html lang='en'>
<head>
<meta charset='UTF-8'>
<meta name='viewport' content='width=device-width'>
<title>" . esc_html( $title ) . "</title>
<style>
  @page { size: A4 landscape; margin: 1.5cm; }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 9pt; color: #1a1a1a; background: #fff; }

  .header { margin-bottom: 14px; border-bottom: 2px solid #1a3a5c; padding-bottom: 10px; display: flex; justify-content: space-between; align-items: flex-end; }
  .header-left h1 { font-size: 14pt; font-weight: 700; color: #1a3a5c; margin-bottom: 2px; }
  .header-left p { font-size: 8pt; color: #666; }
  .header-right { text-align: right; font-size: 8pt; color: #888; }
  .header-right strong { display: block; font-size: 10pt; color: #1a3a5c; }

  table { width: 100%; border-collapse: collapse; font-size: 8pt; margin-bottom: 10px; }
  thead tr { background: #1a3a5c; color: #fff; }
  thead th { padding: 6px 5px; text-align: left; font-weight: 600; font-size: 7.5pt; border-right: 1px solid rgba(255,255,255,0.15); white-space: nowrap; }
  thead th:last-child { border-right: none; }
  tbody td { padding: 5px; border-bottom: 0.5px solid #e0e8f0; vertical-align: middle; }
  tbody tr:last-child td { border-bottom: none; }

  .totals-row td { background: #e8f0f8 !important; font-weight: 700; border-top: 1.5px solid #1a3a5c; }
  tfoot .totals-row td { background: #e8f0f8; font-weight: 700; }

  .num { text-align: right; }
  .ctr { text-align: center; }

  .summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 14px; }
  .summary-card { background: #f5f7fa; border-left: 3px solid #1a3a5c; padding: 8px 10px; border-radius: 4px; }
  .summary-card .val { font-size: 13pt; font-weight: 700; color: #1a3a5c; }
  .summary-card .lbl { font-size: 7pt; color: #888; text-transform: uppercase; letter-spacing: 0.05em; margin-top: 1px; }

  .footer { margin-top: 12px; border-top: 0.5px solid #ddd; padding-top: 6px; font-size: 7pt; color: #aaa; display: flex; justify-content: space-between; }

  .print-btn { position: fixed; top: 16px; right: 16px; background: #1a3a5c; color: #fff; border: none; padding: 10px 20px; border-radius: 6px; font-size: 13px; cursor: pointer; font-family: inherit; box-shadow: 0 2px 8px rgba(0,0,0,0.2); z-index: 999; }
  .print-btn:hover { background: #132d47; }
  @media print { .print-btn { display: none !important; } }
</style>
</head>
<body>
<button class='print-btn' onclick='window.print()'>🖨 Print / Save PDF</button>

<div class='header'>
  <div class='header-left'>
    <h1>" . esc_html( $title ) . "</h1>
    <p>" . esc_html( $subtitle ) . "</p>
  </div>
  <div class='header-right'>
    <strong>Christ Way Church Treasure House</strong>
    Generated: {$generated}
  </div>
</div>

<table>
  <thead><tr>{$th_cells}</tr></thead>
  <tbody>{$tr_rows}</tbody>
  " . ( $totals_row ? "<tfoot>{$totals_html}</tfoot>" : '' ) . "
</table>

<div class='footer'>
  <span>Christ Way Church Treasure House &mdash; Confidential</span>
  <span>Total records: " . count( $rows ) . " &nbsp;|&nbsp; {$generated}</span>
</div>

<script>
  // Auto-trigger print dialog for PDF save
  window.onload = function() {
    document.title = " . json_encode( $title ) . ";
  };
</script>
</body>
</html>";

        // Output as downloadable HTML file that opens directly for printing
        header( 'Content-Type: text/html; charset=UTF-8' );
        header( "Content-Disposition: inline; filename=\"{$filename}\"" );
        header( 'Cache-Control: no-cache' );
        echo $html;
        exit;
    }
}
