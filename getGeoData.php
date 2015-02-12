<?php

header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json');

function escapeJsonString($value) { # list from www.json.org: (\b backspace, \f formfeed)
    $escapers = array("\\", "/", "\"", "\n", "\r", "\t", "\x08", "\x0c");
    $replacements = array("\\\\", "\\/", "\\\"", "\\n", "\\r", "\\t", "\\f", "\\b");
    $result = str_replace($escapers, $replacements, $value);
    return $result;
}

# Retrive URL variables
$geotable = filter_input(INPUT_GET, 'layer');
if (!$geotable) {
    echo "missing required parameter: <i>layer</i>";
    exit;
}

$obj_key = filter_input(INPUT_GET, 'key');
if (!$obj_key) {
    echo "missing required parameter: <i>key</i>";
    exit;
}

$group = filter_input(INPUT_GET, 'group');
if (!$group) {
    echo "missing required parameter: <i>group</i>";
    exit;
}

require 'BD.php';

# Try query or error
$rs = BD::selectGeoData($geotable);
if (!$rs) {
    echo "An SQL error occured.\n";
    exit;
}

# Build GeoJSON
$output = '';
$rowOutput = '';

while ($row = pg_fetch_assoc($rs)) {
    $rowOutput = (strlen($rowOutput) > 0 ? ',' : '') . '{"type": "Feature", "geometry": ' . $row['geojson'] . ', "properties": {';
    $props = '';
    $id = '';
    foreach ($row as $key => $val) {
        if ($key != "geojson") {
            $props .= (strlen($props) > 0 ? ',' : '') . '"' . $key . '":"' . escapeJsonString($val) . '"';
        }
        if ($key == "id") {
            $id .= ',"id":"' . escapeJsonString($val) . '"';
        }
    }

    $rowOutput .= $props . '}';
    $rowOutput .= $id;
    $rowOutput .= '}';
    $output .= $rowOutput;
}

$output = json_decode('{ "type": "FeatureCollection", "features": [ ' . $output . ' ]}');

$output_obj = array("data" => $output, "key" => $obj_key, "group" => $group);

echo json_encode($output_obj);
