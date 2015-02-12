<?php

header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

require 'BD.php';

$action = filter_input(INPUT_GET, 'action');
if ($action === NULL) {
    $action = filter_input(INPUT_POST, 'action');
}
//var_dump($action);
if ($action === NULL || !in_array($action, array('insert', 'insertForm', 'update', 'updateForm', 'delete', 'endSession'))) {
    print_error('Ação inválida.');
}

if ($action === 'endSession') {
    session_destroy();
    header('Location:  ../index.php');
    exit;
}

$tablename = filter_input(INPUT_GET, 'table');
if ($tablename === NULL) {
    $tablename = filter_input(INPUT_POST, 'table');
}
if ($tablename === NULL || pg_num_rows(BD::getTable($tablename)) === 0) {
    print_error('Relação inválida.');
}

include 'HTMLForms.php';
$forms = new HTMLForms();

$tableInf = BD::getTableInfo($tablename);
$form_action = $action;

$return = [];

$table_id = filter_input(INPUT_GET, 'table_id');
if ($table_id === NULL) {
    $table_id = filter_input(INPUT_POST, 'table_id');
}

switch ($action) {
    case 'insert':
        $args = [];
        $ids = [];
        while ($column = pg_fetch_assoc($tableInf)) {
            if ($column['primary_key'] === 't' && $column["default"] !== '') {
                continue;
            }
            if ($column['typ'] === 'integer' || $column['typ'] === 'double precision') {
                if (is_numeric(filter_input(INPUT_POST, $column['name']))) {
                    array_push($args, filter_input(INPUT_POST, $column['name']));
                } else {
                    array_push($args, 0);
                }
            } else {
                array_push($args, filter_input(INPUT_POST, $column['name']));
            }
        }

        $result = BD::insert($tablename, $args);
        if (!$result) {
            print_error("Erro na inserção: " . pg_last_error());
        } else {
            print_inf(array("info" => "Registo inserido com sucesso."));
        }
        break;
    case 'insertForm':
        $return['form'] = $forms->getForm($tablename, $form_action);
        echo $return['form'];
        break;
    case 'update':
        $args = [];
        $old_ids = [];
        $new_ids = [];
        while ($column = pg_fetch_assoc($tableInf)) {
            array_push($args, filter_input(INPUT_POST, $column['name']));
            if ($column['primary_key'] === 't') {
                $name = filter_input(INPUT_POST, $column['name']);
                array_push($old_ids, filter_input(INPUT_POST, 'id_' . $column['name']));
                array_push($new_ids, filter_input(INPUT_POST, $column['name']));
            }
        }

        if (BD::exists($tablename, $old_ids) === 0) {
            print_error("Registo não existente.");
        }
        if ($old_ids != $new_ids) {
            if (BD::exists($tablename, $new_ids)) {
                print_error("Registo já existente.");
            }
        }

        $result = BD::update($tablename, $old_ids, $args);
        if (!$result) {
            print_error("Erro na inserção: " . pg_last_error());
        } else {
            print_inf(array("info" => "Registo atualizado com sucesso."));
        }

        break;
    case 'updateForm':
        $args = [];
        $ids = [];
        while ($column = pg_fetch_assoc($tableInf)) {
            if ($column['primary_key'] === 't') {
                array_push($ids, filter_input(INPUT_GET, $column['name']));
            }
        }

        $data = BD::select($tablename, $ids);

        if (pg_num_rows($data) === 0) {
            print_error("Registo não existente.");
        }

        $return['form'] = $forms->getForm($tablename, $form_action, pg_fetch_assoc($data));
        echo $return['form'];
        break;
    case 'delete':
        $args = [];
        while ($column = pg_fetch_assoc($tableInf)) {
            if ($column['primary_key'] === 't') {
                array_push($args, filter_input(INPUT_GET, $column['name']));
            }
        }

        if (BD::exists($tablename, $args) === 0) {
            print_error("Registo não existente.");
        }

        $result = BD::delete($tablename, $args);

        if (!$result) {
            print_error("Erro na remoção: " . pg_last_error());
        } else {
            print_inf(array("info" => "Registo removido com sucesso."));
        }
        break;
}