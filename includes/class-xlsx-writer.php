<?php
/**
 * Native PHP XLSX Writer
 * Zero dependencies — uses PHP's built-in ZipArchive extension
 * Produces fully valid .xlsx files compatible with Excel, LibreOffice, Google Sheets
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class CWR_XlsxWriter {

    private $sheets = [];
    private $shared_strings = [];
    private $ss_index = [];

    public function add_sheet( $name, array $headers, array $rows, array $header_style = [] ) {
        $this->sheets[] = compact( 'name', 'headers', 'rows', 'header_style' );
    }

    private function shared_string( $val ) {
        $val = (string) $val;
        if ( ! isset( $this->ss_index[ $val ] ) ) {
            $this->ss_index[ $val ] = count( $this->shared_strings );
            $this->shared_strings[] = $val;
        }
        return $this->ss_index[ $val ];
    }

    private function col_letter( $n ) {
        $letters = '';
        while ( $n >= 0 ) {
            $letters = chr( $n % 26 + 65 ) . $letters;
            $n = intval( $n / 26 ) - 1;
        }
        return $letters;
    }

    private function cell( $col, $row ) {
        return $this->col_letter( $col ) . $row;
    }

    private function xml_val( $v ) {
        return htmlspecialchars( (string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
    }

    private function build_sheet_xml( $sheet ) {
        $rows_xml = '';
        $row_num  = 1;

        // Header row
        $row_cells = '';
        foreach ( $sheet['headers'] as $ci => $h ) {
            $si = $this->shared_string( $h );
            $ref = $this->cell( $ci, $row_num );
            $row_cells .= "<c r=\"{$ref}\" t=\"s\" s=\"1\"><v>{$si}</v></c>";
        }
        $rows_xml .= "<row r=\"{$row_num}\" customHeight=\"1\" ht=\"18\">{$row_cells}</row>";
        $row_num++;

        // Data rows
        foreach ( $sheet['rows'] as $ri => $row ) {
            $row_cells = '';
            $bg_style  = ( $ri % 2 === 0 ) ? '2' : '0';
            foreach ( $row as $ci => $val ) {
                $ref = $this->cell( $ci, $row_num );
                if ( is_numeric( $val ) && $val !== '' ) {
                    $row_cells .= "<c r=\"{$ref}\" s=\"{$bg_style}\"><v>" . $this->xml_val( $val ) . "</v></c>";
                } else {
                    $si = $this->shared_string( (string) $val );
                    $row_cells .= "<c r=\"{$ref}\" t=\"s\" s=\"{$bg_style}\"><v>{$si}</v></c>";
                }
            }
            // Totals row styling
            if ( isset( $sheet['rows'][ $ri + 1 ] ) === false && count( $sheet['rows'] ) > 1 ) {
                // last row — apply totals style
            }
            $rows_xml .= "<row r=\"{$row_num}\">{$row_cells}</row>";
            $row_num++;
        }

        $last_col = $this->col_letter( max( 0, count( $sheet['headers'] ) - 1 ) );
        $last_row = $row_num - 1;
        $dim      = "A1:{$last_col}{$last_row}";

        // Column widths
        $col_widths = '';
        foreach ( $sheet['headers'] as $ci => $h ) {
            $width = max( 12, min( 40, strlen( $h ) + 4 ) );
            $col_widths .= "<col min=\"" . ($ci+1) . "\" max=\"" . ($ci+1) . "\" width=\"{$width}\" bestFit=\"1\" customWidth=\"1\"/>";
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
                xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
            "<dimension ref=\"{$dim}\"/>" .
            "<sheetViews><sheetView tabSelected=\"1\" workbookViewId=\"0\"><selection activeCell=\"A1\" sqref=\"A1\"/></sheetView></sheetViews>" .
            "<sheetFormatPr defaultRowHeight=\"15\" customHeight=\"1\"/>" .
            "<cols>{$col_widths}</cols>" .
            "<sheetData>{$rows_xml}</sheetData>" .
            "<autoFilter ref=\"{$dim}\"/>" .
            "</worksheet>";
    }

    private function build_shared_strings_xml() {
        $count = count( $this->shared_strings );
        $items = '';
        foreach ( $this->shared_strings as $s ) {
            $items .= '<si><t xml:space="preserve">' . $this->xml_val( $s ) . '</t></si>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            "<sst xmlns=\"http://schemas.openxmlformats.org/spreadsheetml/2006/main\" count=\"{$count}\" uniqueCount=\"{$count}\">{$items}</sst>";
    }

    private function build_workbook_xml() {
        $sheets_xml = '';
        foreach ( $this->sheets as $i => $sheet ) {
            $rid = $i + 1;
            $sid = $i + 1;
            $sheets_xml .= "<sheet name=\"" . $this->xml_val( $sheet['name'] ) . "\" sheetId=\"{$sid}\" r:id=\"rId{$rid}\"/>";
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
                xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
            '<fileVersion appName="xl" lastEdited="5" lowestEdited="5"/>' .
            '<workbookPr date1904="0"/>' .
            "<sheets>{$sheets_xml}</sheets>" .
            '</workbook>';
    }

    private function build_workbook_rels() {
        $rels = '';
        foreach ( $this->sheets as $i => $sheet ) {
            $rid = $i + 1;
            $rels .= "<Relationship Id=\"rId{$rid}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet\" Target=\"worksheets/sheet{$rid}.xml\"/>";
        }
        $ss_rid = count( $this->sheets ) + 1;
        $rels   .= "<Relationship Id=\"rId{$ss_rid}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings\" Target=\"sharedStrings.xml\"/>";
        $st_rid  = $ss_rid + 1;
        $rels   .= "<Relationship Id=\"rId{$st_rid}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles\" Target=\"styles.xml\"/>";
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            $rels . '</Relationships>';
    }

    private function build_styles_xml() {
        // Style 0: normal, Style 1: header (navy bg, white bold), Style 2: striped row
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="3">
    <font><sz val="11"/><name val="Calibri"/></font>
    <font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
    <font><b/><sz val="11"/><name val="Calibri"/></font>
  </fonts>
  <fills count="4">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF1A3A5C"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFF0F5FA"/></patternFill></fill>
  </fills>
  <borders count="2">
    <border><left/><right/><top/><bottom/><diagonal/></border>
    <border><left style="thin"><color rgb="FFD0D9E3"/></left><right style="thin"><color rgb="FFD0D9E3"/></right><top style="thin"><color rgb="FFD0D9E3"/></top><bottom style="thin"><color rgb="FFD0D9E3"/></bottom></border>
  </borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs count="4">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0"><alignment wrapText="0"/></xf>
    <xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0"><alignment horizontal="left" vertical="center"/></xf>
    <xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0"/>
    <xf numFmtId="0" fontId="2" fillId="3" borderId="1" xfId="0"/>
  </cellXfs>
</styleSheet>';
    }

    private function build_content_types() {
        $sheets_ct = '';
        foreach ( $this->sheets as $i => $sheet ) {
            $n = $i + 1;
            $sheets_ct .= "<Override PartName=\"/xl/worksheets/sheet{$n}.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml\"/>";
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
            '<Default Extension="xml" ContentType="application/xml"/>' .
            '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
            $sheets_ct .
            '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>' .
            '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' .
            '</Types>';
    }

    public function output( $filename ) {
        $tmp = tempnam( sys_get_temp_dir(), 'cwr_xlsx_' ) . '.xlsx';

        $zip = new ZipArchive();
        if ( $zip->open( $tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
            wp_die( 'Could not create Excel file. Please ensure ZipArchive PHP extension is enabled.' );
        }

        // Build shared strings (must process all sheets first)
        $sheet_xmls = [];
        foreach ( $this->sheets as $sheet ) {
            $sheet_xmls[] = $this->build_sheet_xml( $sheet );
        }

        $zip->addFromString( '[Content_Types].xml',       $this->build_content_types() );
        $zip->addFromString( '_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
            '</Relationships>'
        );
        $zip->addFromString( 'xl/workbook.xml',       $this->build_workbook_xml() );
        $zip->addFromString( 'xl/_rels/workbook.xml.rels', $this->build_workbook_rels() );
        $zip->addFromString( 'xl/sharedStrings.xml',  $this->build_shared_strings_xml() );
        $zip->addFromString( 'xl/styles.xml',         $this->build_styles_xml() );

        foreach ( $sheet_xmls as $i => $xml ) {
            $zip->addFromString( 'xl/worksheets/sheet' . ($i+1) . '.xml', $xml );
        }

        $zip->close();

        header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
        header( "Content-Disposition: attachment; filename=\"{$filename}\"" );
        header( 'Content-Length: ' . filesize( $tmp ) );
        header( 'Cache-Control: max-age=0' );
        readfile( $tmp );
        unlink( $tmp );
        exit;
    }
}
