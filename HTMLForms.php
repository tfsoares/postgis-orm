<?php

class HTMLForms {

    var $dictionary;

    public function HTMLForms($dictionary = []) {
        $this->dictionary = $dictionary;
    }

    private function translate($key) {
        if (array_key_exists($key, $this->dictionary)) {
            return $this->dictionary[$key];
        } else {
            return $key;
        }
    }

    /*
     * $tableInf - column information
     * $form_action - where to point to
     * $operation - insert OR update
     * $args - used in case of $operation = 'update'
     */

    public function getForm($tablename, $form_action, $args = []) {
        $operation = "update";
        if (empty($args)) {
            $operation = "insert";
        }

        $tableInf = BD::getTableInfo($tablename);

        $form = "<form id='updateForm' action='server/action.php' method='post'>";
        $form .= $this->getHiddenInput("action", explode("F", $form_action)[0]);
        $form .= $this->getHiddenInput("table", $tablename);

        while ($column = pg_fetch_array($tableInf)) {
            if (array_key_exists($column["name"], $args)) {
                $field_value = $args[$column["name"]];
            } else {
                $field_value = "";
            }

            if ($column["primary_key"] === "t") {
                $form = $form . $this->getHiddenInput("id_" . $column["name"], $field_value);

                if ($operation === "update") {
                    $form .= $this->getTextInput($column["name"], $column["typ"], $field_value);
                }
            } else {
                switch ($column['typ']) {
                    case 'text':
                        $form .= $this->getTextArea($column["name"], $column["typ"], $field_value);
                        break;
                    default:
                        $form .= $this->getTextInput($column["name"], $column["typ"], $field_value);
                }
            }

            $form .= "</br>";
        }

        $form .= "<input type='submit' class='btn btn-primary pull-right' value='Guardar' />"
                . "<input id='$form_action-FormBack' class='btn btn-default pull-right'type='button' value='Voltar'/>";

        return $form . "</form>";
    }

    private function getHiddenInput($name, $val) {
        $input = "<input name='$name' type='hidden' value='$val'></input>";

        return $input;
    }

    private function getTextInput($name, $validation, $value = "") {
        $trans_name = $this->translate($name);
        $label = "<label class='col-md-4 control-label' for='$name'>$trans_name:</label>";
        $input = "<input class='form-control input-md'name='$name' type='text' validate='$validation' value='$value'></input>";

        return $label . $input;
    }

    private function getTextArea($name, $validation, $value = "") {
        $trans_name = $this->translate($name);
        $label = "<label class='col-md-4 control-label' for='$name'>$trans_name:</label>";
        $textarea = "<textarea class='form-control input-md' name='$name' rows='6' validate='$validation'>$value</textarea>";

        return $label . $textarea;
    }

    public function setDictionary($dictionary) {
        $this->dictionary = $dictionary;
    }

}