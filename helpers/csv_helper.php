<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

if ( ! function_exists('arrayToCsv')) {
    function arrayToCsv( $fields, $delimiter = ';', $enclosure = '"', $encloseAll = false, $nullToMysqlNull = false ) {
        $outputString = array();
        foreach($fields as $tempFields) {
            $outputString[] = arrayToCsvRow($tempFields, $delimiter, $enclosure, $encloseAll, $nullToMysqlNull);
        }
        return implode("\r\n", $outputString);
    }
}

if (!function_exists('arrayToCsvRow')) {
    function arrayToCsvRow( $fields, $delimiter = ';', $enclosure = '"', $encloseAll = false, $nullToMysqlNull = false ) {
        $delimiter_esc = preg_quote($delimiter, '/');
        $enclosure_esc = preg_quote($enclosure, '/');

        $output = array();
        foreach ( $fields as $field ) {
            if ($field === null && $nullToMysqlNull) {
                $output[] = 'NULL';
                continue;
            }

            if ( $encloseAll || preg_match( "/(?:${delimiter_esc}|${enclosure_esc}|\s)/", $field ) ) {
                $field = $enclosure . str_replace($enclosure, $enclosure . $enclosure, $field) . $enclosure;
            }
            $output[] = $field;
        }
        return implode( $delimiter, $output );
    }
}